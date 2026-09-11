<?php

declare(strict_types=1);

namespace App\Booking\Contracts;

use App\Booking\Exceptions\RecordingIngestionException;
use App\Models\Recording;

/**
 * A recording provider that can dispose of ITS copy of a lesson
 * recording once SIRI holds a verified copy of its own.
 *
 * Called only after a recording has reached Available — stored, read
 * back from the storage backend and matched — and only when the
 * administrator has switched source disposal on. The provider decides
 * what "dispose" means; for Zoom it is the recoverable cloud trash,
 * never a permanent delete. Idempotent: a source that is already gone
 * is a success. Failure is reported, audited and retried by nothing:
 * the SIRI copy is the canonical one and a lingering source is a
 * storage-hygiene matter, not a data-loss risk.
 */
interface DisposesSourceRecordings
{
    /**
     * @return bool true when the source was disposed of (or already was); false when disposal is switched off for this provider
     *
     * @throws RecordingIngestionException when the provider refused
     */
    public function disposeSourceRecording(Recording $recording): bool;
}
