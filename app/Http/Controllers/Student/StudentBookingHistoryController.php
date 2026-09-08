<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Booking\Contracts\BookingRepositoryInterface;
use App\Booking\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Livewire\Frontend\Student\BookingHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class StudentBookingHistoryController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        // Legacy deep link (`/my-bookings?booking=…`) used to open a modal
        // on this page; the booking now has a page of its own.
        $bookingId = $request->query('booking');

        if (is_string($bookingId) && $bookingId !== '') {
            return redirect()->route('dashboard.my-bookings.show', $bookingId);
        }

        return view('student.bookings.index');
    }

    /**
     * Authorization is re-checked here on every request
     * (BookingPolicy::view()) — the Livewire component mounted by this
     * view re-checks it too, so no state change can be driven against a
     * booking this viewer may not see. Cancelled bookings are soft-deleted
     * rather than removed, and the list renders them, so the trashed
     * lookup is what keeps those rows from becoming dead links.
     */
    public function show(Request $request, string $booking, BookingRepositoryInterface $bookings): View
    {
        $model = $bookings->findWithTrashedOrFail($booking);

        Gate::authorize('view', $model);

        return view('student.bookings.show', [
            'booking' => $model,
            'backUrl' => route('dashboard.my-bookings', $this->listState($request)),
        ]);
    }

    /**
     * The list state the student came from (filter + page size + page),
     * carried on every detail link so "Back to My Bookings" returns them
     * where they were instead of the top of an unfiltered list.
     *
     * Rebuilt from a whitelist rather than echoed back: only these three
     * keys survive, each re-validated, so the query string can never be
     * used to point this page's own back link somewhere else.
     *
     * @return array<string, string|int>
     */
    private function listState(Request $request): array
    {
        // The link the student followed wins; the list's own remembered
        // state is the fallback for every entry point that carries none
        // (Payments, the dashboard card, a notification email).
        $source = $request->query();

        if (! array_intersect_key($source, array_flip(['status', 'per_page', 'page']))) {
            $remembered = session(BookingHistory::LIST_STATE_SESSION_KEY);
            $source = is_array($remembered) ? $remembered : [];
        }

        // Every value is read as a scalar first: an array-shaped value
        // (`?status[]=…`) is simply not list state.
        $status = BookingStatus::tryFrom(is_string($raw = $source['status'] ?? null) ? $raw : '');
        $perPage = (int) (is_scalar($raw = $source['per_page'] ?? null) ? $raw : 0);
        $page = (int) (is_scalar($raw = $source['page'] ?? null) ? $raw : 0);

        return array_filter([
            'status' => $status?->value,
            'per_page' => in_array($perPage, BookingHistory::PER_PAGE_OPTIONS, true) ? $perPage : null,
            'page' => $page > 1 ? $page : null,
        ]);
    }
}
