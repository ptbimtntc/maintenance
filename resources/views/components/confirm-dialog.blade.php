{{-- Global confirmation dialog. Opened by forms/buttons carrying data-confirm
     (see resources/js/ui.js); never include more than once per page. --}}
<div
    data-confirm-dialog
    x-data="confirmDialog"
    x-on:confirm-request.window="open($event.detail)"
    x-on:keydown.escape.window="visible && cancel()"
    x-show="visible"
    x-cloak
    class="fixed inset-0 z-[60] flex items-end justify-center px-4 py-6 sm:items-center"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="confirm-dialog-title"
    aria-describedby="confirm-dialog-message"
>
    <div
        x-show="visible"
        x-transition:enter="ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="glass-backdrop fixed inset-0"
        @click="cancel()"
    ></div>

    <div
        x-show="visible"
        x-transition:enter="ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-3 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-100"
        x-transition:leave-start="opacity-100 sm:scale-100"
        x-transition:leave-end="opacity-0 sm:scale-95"
        class="glass-modal relative w-full max-w-md overflow-hidden rounded-lg bg-white shadow-xl"
    >
        <div class="flex gap-4 p-5">
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                :class="tone === 'danger' ? 'bg-danger-100 text-danger-600' : 'bg-brand-100 text-brand-600'"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </span>

            <div class="min-w-0">
                <h2 id="confirm-dialog-title" class="text-base font-semibold text-neutral-900">Are you sure?</h2>
                <p id="confirm-dialog-message" class="mt-1 text-sm text-neutral-600" x-text="message"></p>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-2 bg-neutral-50 px-5 py-3 sm:flex-row sm:justify-end">
            <x-button variant="secondary" type="button" @click="cancel()">Cancel</x-button>
            <button
                type="button"
                x-ref="confirmButton"
                @click="confirm()"
                class="inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-medium text-white shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                :class="tone === 'danger' ? 'bg-danger-600 hover:bg-danger-700 focus-visible:ring-danger-500' : 'bg-brand-600 hover:bg-brand-700 focus-visible:ring-brand-500'"
                x-text="label"
            ></button>
        </div>
    </div>
</div>
