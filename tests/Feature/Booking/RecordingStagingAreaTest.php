<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Booking\Enums\RecordingFailureCode;
use App\Booking\Exceptions\RecordingIngestionException;
use App\Booking\Services\RecordingStagingArea;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The shared staging pump — the one place every streamed provider
 * download passes through on its way to disk. These tests are about
 * what happens WHILE bytes arrive, which is exactly the part a
 * finalize-time check cannot cover: an oversized or endless source
 * must be cut off before it fills the staging disk, a short or failed
 * write must never produce a "complete" staged file, and a connection
 * that closed early must be recognised as an incomplete download.
 */
final class RecordingStagingAreaTest extends TestCase
{
    private RecordingStagingArea $staging;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staging = new RecordingStagingArea(sys_get_temp_dir().'/siri-staging-test-'.uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->staging->path());

        parent::tearDown();
    }

    /** @return resource */
    private function source(string $bytes)
    {
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, $bytes);
        rewind($stream);

        return $stream;
    }

    /** @return resource */
    private function sink()
    {
        return fopen('php://temp', 'w+b');
    }

    private function mp4Bytes(int $padding = 200): string
    {
        return "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2avc1mp41".str_repeat("\x00", $padding);
    }

    // ── Size ceiling, during streaming ────────────────────────────────

    /**
     * Three 1 MiB chunks arrive; the ceiling is 1.5 MiB. The pump must
     * stop after the chunk that crosses the ceiling — never read the
     * third chunk, never write the whole source to disk.
     */
    public function test_the_size_ceiling_is_enforced_while_bytes_arrive_not_after_the_whole_source_is_on_disk(): void
    {
        config(['recordings.max_source_bytes' => (int) (1.5 * 1024 * 1024)]);
        $source = $this->source(str_repeat('a', 3 * 1024 * 1024));
        $sink = $this->sink();

        try {
            $this->staging->pump($source, $sink);
            $this->fail('Expected the oversized source to be rejected.');
        } catch (RecordingIngestionException $e) {
            $this->assertSame(RecordingFailureCode::SourceRejected, $e->failureCode);
            $this->assertTrue($e->failureCode->isPermanent(), 'an oversized source is never retried');
        }

        $this->assertLessThanOrEqual(2 * 1024 * 1024, ftell($source), 'reading stopped once the ceiling was crossed');
        $this->assertLessThan(3 * 1024 * 1024, ftell($sink), 'the whole source was never written to disk');
        $this->assertLessThanOrEqual((int) (1.5 * 1024 * 1024), ftell($sink), 'nothing beyond the ceiling reached the sink');
    }

    /** A declared length above the ceiling is refused before the first byte is read. */
    public function test_a_declared_length_above_the_ceiling_is_refused_before_any_byte_is_read(): void
    {
        config(['recordings.max_source_bytes' => 100]);
        $source = $this->source(str_repeat('a', 50));

        try {
            $this->staging->pump($source, $this->sink(), expectedBytes: 101);
            $this->fail('Expected the declared length to be refused.');
        } catch (RecordingIngestionException $e) {
            $this->assertSame(RecordingFailureCode::SourceRejected, $e->failureCode);
        }

        $this->assertSame(0, ftell($source), 'not a single byte was read');
    }

    /** The declared length is a hint for early refusal and completeness — never a substitute for counting. */
    public function test_a_source_that_sends_more_than_it_declared_is_still_cut_off_at_the_ceiling(): void
    {
        config(['recordings.max_source_bytes' => 100]);
        $source = $this->source(str_repeat('a', 500));

        $this->expectException(RecordingIngestionException::class);
        $this->expectExceptionMessage('exceeds the configured ceiling');

        $this->staging->pump($source, $this->sink(), expectedBytes: 90);
    }

    // ── Incomplete reads ──────────────────────────────────────────────

    /**
     * A socket that drops mid-body just reports EOF. With the declared
     * length in hand the pump can tell that apart from a finished
     * download — and it is transient, because a retry re-downloads.
     */
    public function test_a_stream_that_ends_short_of_its_declared_length_is_an_incomplete_download(): void
    {
        $source = $this->source(str_repeat('a', 60));

        try {
            $this->staging->pump($source, $this->sink(), expectedBytes: 100);
            $this->fail('Expected the short read to be reported.');
        } catch (RecordingIngestionException $e) {
            $this->assertSame(RecordingFailureCode::SourceDownloadFailed, $e->failureCode);
            $this->assertFalse($e->failureCode->isPermanent(), 'an interrupted download is retried');
            $this->assertStringContainsString('received 60 of 100', $e->getMessage());
        }
    }

    public function test_a_complete_stream_matching_its_declared_length_is_copied_byte_for_byte(): void
    {
        $bytes = str_repeat('b', 3000);
        $sink = $this->sink();

        $written = $this->staging->pump($this->source($bytes), $sink, expectedBytes: 3000);

        rewind($sink);
        $this->assertSame(3000, $written);
        $this->assertSame($bytes, stream_get_contents($sink));
    }

    public function test_a_stream_without_a_declared_length_is_accepted_on_eof(): void
    {
        $bytes = str_repeat('c', 1234);
        $sink = $this->sink();

        $this->assertSame(1234, $this->staging->pump($this->source($bytes), $sink));
        rewind($sink);
        $this->assertSame($bytes, stream_get_contents($sink));
    }

    // ── Failed writes ─────────────────────────────────────────────────

    /** fwrite() to an unwritable handle returns false or 0 without throwing; the pump must not accept that as success. */
    public function test_a_failed_write_aborts_instead_of_staging_a_truncated_file(): void
    {
        $readOnly = $this->staging->path('read-only.part');
        file_put_contents($readOnly, '');
        $sink = fopen($readOnly, 'rb'); // a read-only handle: every write fails

        try {
            $this->staging->pump($this->source(str_repeat('d', 10)), $sink);
            $this->fail('Expected the failed write to abort the transfer.');
        } catch (RecordingIngestionException $e) {
            $this->assertSame(RecordingFailureCode::SourceDownloadFailed, $e->failureCode);
            $this->assertStringContainsString('staging write failed', $e->getMessage());
        } finally {
            fclose($sink);
        }
    }

    // ── Cleanup on failure ────────────────────────────────────────────

    /** Whatever the pump throws, stageStream() removes the partial .part file it had opened. */
    public function test_a_transfer_that_fails_mid_stream_leaves_no_partial_file_in_the_staging_area(): void
    {
        config(['recordings.max_source_bytes' => 100]);
        $source = $this->source(str_repeat('e', 5000));

        try {
            $this->staging->stageStream(
                fn ($handle) => $this->staging->pump($source, $handle),
                'zoom-recording.mp4',
                'video/mp4',
            );
            $this->fail('Expected the oversized source to be rejected.');
        } catch (RecordingIngestionException) {
            // expected
        }

        $this->assertCount(0, File::files($this->staging->path()), 'no .part or staged file may remain after a failure');
    }

    public function test_an_incomplete_download_leaves_no_partial_file_in_the_staging_area(): void
    {
        $source = $this->source($this->mp4Bytes());

        try {
            $this->staging->stageStream(
                fn ($handle) => $this->staging->pump($source, $handle, expectedBytes: strlen($this->mp4Bytes()) + 1),
                'zoom-recording.mp4',
                'video/mp4',
            );
            $this->fail('Expected the incomplete download to be rejected.');
        } catch (RecordingIngestionException) {
            // expected
        }

        $this->assertCount(0, File::files($this->staging->path()));
    }

    public function test_a_successful_pump_through_stage_stream_produces_a_verified_staged_file(): void
    {
        $bytes = $this->mp4Bytes();

        $staged = $this->staging->stageStream(
            fn ($handle) => $this->staging->pump($this->source($bytes), $handle, expectedBytes: strlen($bytes)),
            'zoom-recording.mp4',
            'video/mp4',
        );

        $this->assertSame(strlen($bytes), $staged->sizeBytes);
        $this->assertSame(hash('sha256', $bytes), $staged->checksum);
        $this->assertSame('video/mp4', $staged->mimeType);
        $this->assertFileExists($staged->absolutePath);
        $this->assertStringEndsNotWith('.part', $staged->absolutePath);

        $staged->delete();
    }
}
