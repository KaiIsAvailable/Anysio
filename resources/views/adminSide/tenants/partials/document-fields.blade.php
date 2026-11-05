                    <!-- Photo / SSM Certificate -->
                    <div class="mb-6">
                        <x-form.input-label for="ic_photo_path" id="photo_label" value="{{ $tenant->tenant_type === 'corporate' ? 'SSM Certificate' : 'Upload photocopy IC (for rental purpose)' }}" class="mb-1" />
                        <p class="text-xs text-gray-500 mb-2" id="photo_helper">Please ask user to scratch IC before upload.</p>
                        
                        @php
                            $isCorporate = $tenant->tenant_type === 'corporate';
                            $currentPath = $isCorporate ? $tenant->user->companies->first()?->ssm_cert_path : $tenant->ic_photo_path;
                            
                            // Safely check mime type without crashing if file is missing from disk
                            $isPdf = false;
                            if ($currentPath) {
                                if (str_ends_with(strtolower($currentPath), '.pdf')) {
                                    $isPdf = true;
                                } else {
                                    try {
                                        $mime = app(\App\Services\FileService::class)->getFileMimeType($currentPath);
                                        $isPdf = ($mime === 'application/pdf');
                                    } catch (\Exception $e) {
                                        $isPdf = false;
                                    }
                                }
                            }
                        @endphp

                        <!-- Individual Photo Preview -->
                        <div id="individual_photo_preview_container" class="mb-2 {{ (!$isCorporate && $currentPath) ? '' : 'hidden' }}">
                            @if(!$isCorporate && $currentPath)
                                @if($isPdf)
                                    <a href="{{ route('admin.tenants.ic_photo', basename($currentPath)) }}" target="_blank" rel="noopener" class="text-indigo-600 underline text-sm font-medium">Open current IC/Passport PDF</a>
                                @else
                                    <img src="{{ route('admin.tenants.ic_photo', basename($currentPath)) }}" alt="Current Photo" class="h-40 w-40 object-cover rounded-lg border border-gray-200">
                                @endif
                            @endif
                        </div>

                        <!-- Corporate SSM Cert Preview -->
                        <div id="corporate_photo_preview_container" class="mb-2 {{ ($isCorporate && $currentPath) ? '' : 'hidden' }}">
                            @if($isCorporate && $currentPath)
                                @if($isPdf)
                                    <a href="{{ route('admin.tenants.ic_photo', basename($currentPath)) }}" target="_blank" rel="noopener" class="text-indigo-600 underline text-sm font-medium">Open current SSM Certificate PDF</a>
                                @else
                                    <img src="{{ route('admin.tenants.ic_photo', basename($currentPath)) }}" alt="Current SSM Cert" class="h-40 w-40 object-cover rounded-lg border border-gray-200">
                                @endif
                            @endif
                        </div>

                        <!-- Individual File Input -->
                        <div id="individual_photo_input_container" class="{{ $isCorporate ? 'hidden' : '' }}">
                            <x-form.file-input name="ic_photo_path" id="ic_photo_path" accept="image/jpeg,image/png,image/gif,application/pdf" onchange="previewImage(this, 'ic_preview', 'individual_photo_preview_container')" class="w-full" />
                        </div>

                        <!-- Corporate File Input -->
                        <div id="corporate_photo_input_container" class="{{ $isCorporate ? '' : 'hidden' }}">
                            <x-form.file-input name="ssm_cert_path" id="ssm_cert_path" accept="image/jpeg,image/png,image/gif,application/pdf" onchange="previewImage(this, 'ssm_preview', 'corporate_photo_preview_container')" class="w-full" />
                        </div>

                        <p class="text-xs text-gray-500 mt-1">Leave empty to keep current file.</p>
                        <x-form.input-error :messages="$errors->get('ic_photo_path')" class="mt-1" />
                        <x-form.input-error :messages="$errors->get('ssm_cert_path')" class="mt-1" />
                    </div>

