@props(['action', 'id' => 'complaint-create'])

<dialog id="{{ $id }}" data-complaint-modal aria-labelledby="{{ $id }}-title" class="w-full max-w-md rounded-2xl p-0 shadow-xl backdrop:bg-slate-900/50">
            <form data-complaint-form action="{{ $action }}" method="POST" class="p-6 space-y-4">
                @csrf
                <h2 id="{{ $id }}-title" class="text-xl font-bold text-slate-900">Complaint</h2>
                <div>
                    <label for="{{ $id }}-subject" class="block text-sm font-semibold text-gray-700 mb-2">Subject</label>
                    <input id="{{ $id }}-subject" name="subject" required maxlength="255" class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" autofocus>
                </div>
                <p data-create-error role="alert" class="text-sm whitespace-pre-line text-red-700"></p>
                <div class="flex justify-end gap-2">
                    <button type="button" data-cancel-complaint class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700">Cancel</button>
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Create</button>
                </div>
            </form>
        </dialog>
