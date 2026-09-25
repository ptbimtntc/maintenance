<x-app-layout>
    <x-slot name="header">Generate QR Codes</x-slot>

    <div class="space-y-4 print:space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
            <p class="text-sm text-neutral-500">Certificate verification QR code for each employee - scan or print for a badge. Links to the same public page as the one on an employee's profile.</p>
            <div class="flex items-center gap-2">
                <a href="{{ route('employees.index') }}" class="rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">&larr; Back</a>
                <button type="button" onclick="window.print()" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">Print All</button>
            </div>
        </div>

        <form method="GET" class="flex gap-2 print:hidden">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search NIK, name, department..." class="w-full max-w-sm rounded-md border-neutral-300 text-sm">
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">Search</button>
            @if (filled($filters['search'] ?? null))
                <a href="{{ route('employees.qr-codes') }}" class="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">Reset</a>
            @endif
        </form>

        <div id="qr-base-url-panel" data-default-base-url="{{ url('/') }}" class="rounded-lg border border-neutral-200 bg-white p-4 shadow-md print:hidden">
            <label for="qr-base-url" class="block text-sm font-medium text-neutral-700">Base URL used in the QR codes</label>
            <p class="mt-1 text-xs text-neutral-500">
                Defaults to this site's current address. Type a different domain here (e.g. the production domain
                this will eventually run on) if you want the QR codes to point there instead - saved in this browser
                only, nothing on the server changes.
            </p>
            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                <input type="text" id="qr-base-url" placeholder="https://your-domain.example.com"
                       class="w-full min-w-0 flex-1 rounded-md border-neutral-300 text-sm">
                <button type="button" id="qr-base-url-apply" class="shrink-0 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700">Apply</button>
                <button type="button" id="qr-base-url-reset" class="shrink-0 rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">Reset to default</button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 print:grid-cols-3">
            @forelse ($employees as $employee)
                @php $path = route('verify.employee', $employee, false); @endphp
                <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-md text-center print:break-inside-avoid">
                    <h3 class="truncate text-sm font-semibold text-neutral-900">{{ $employee->full_name }}</h3>
                    <p class="text-xs text-neutral-500">NIK: {{ $employee->employee_number }}</p>
                    <div class="my-3 flex justify-center">
                        <canvas data-qrcode data-qr-path="{{ $path }}" class="h-[160px] w-[160px] rounded-md border border-neutral-200 bg-white"></canvas>
                    </div>
                    <p class="break-all text-[10px] text-neutral-400" data-qr-url-text></p>
                    <button type="button" data-download-qr data-filename="{{ $employee->employee_number }}"
                            data-name="{{ $employee->full_name }}" data-nik="{{ $employee->employee_number }}"
                            class="mt-2 w-full rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50 print:hidden">
                        Download PNG
                    </button>
                </div>
            @empty
                <div class="col-span-full rounded-lg border border-neutral-200 bg-white p-10 text-center text-neutral-500 shadow-md">No employees found.</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
