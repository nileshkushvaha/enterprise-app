<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ContactFormController extends Controller
{
    /**
     * Handle contact form submission from block
     */
    public function submit(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'message' => 'required|string',
        ]);

        // Send email or save to database
        // Example: Mail::send(...) or ContactSubmission::create($validated)

        return back()->with('success', 'Thank you for your message. We\'ll get back to you soon!');
    }
}
