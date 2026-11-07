@once
<script>
    window.viewAgreement = async function(button) {
        const requestId = Symbol('agreement-preview');
        window.dispatchEvent(new CustomEvent('open-lease-preview-loading', {
            detail: { requestId, title: button.dataset.title || 'Agreement Preview' }
        }));
        // Let the modal and skeleton paint before processing the template.
        await new Promise(requestAnimationFrame);
        await new Promise(requestAnimationFrame);

        try {
            let content = button.dataset.baseContent;
            if (!content) {
                throw new Error('Agreement content is empty.');
            }

            let replacements;
            try {
                replacements = JSON.parse(button.dataset.replacements);
            } catch (error) {
                throw new Error('Unable to load agreement data.');
            }

            Object.keys(replacements).forEach(key => {
                const val = String(replacements[key] ?? 'N/A').replace(/[&<>"']/g, char => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[char]));
                const safeVal = val;

                // Replace standard or encoded curly brackets variants
                const textRegex = new RegExp(`(?:\\{|&#123;|&lcub;){1,2}(?:[\\s\\u200B\\u200C\\u200D\\uFEFF&nbsp;]|<[^>]*>)*${key}(?:[\\s\\u200B\\u200C\\u200D\\uFEFF&nbsp;]|<[^>]*>)*(?:\\}|&#125;|&rcub;){1,2}`, 'gi');
                content = content.replace(textRegex, () => safeVal);
            });

            window.dispatchEvent(new CustomEvent('open-lease-preview', {
                detail: { requestId, content: content, title: button.dataset.title,
                    allowBreakdown: button.dataset.rentalBreakdown === 'true',
                    rentalBasis: JSON.parse(button.dataset.rentalBasis || 'null') }
            }));
        } catch (error) {
            window.dispatchEvent(new CustomEvent('lease-preview-error', {
                detail: { requestId, message: error.message || 'Unable to generate agreement preview.' }
            }));
        }
    };

    window.viewRefund = async function(button) {
        const requestId = Symbol('refund-preview');
        window.dispatchEvent(new CustomEvent('open-lease-preview-loading', {
            detail: { requestId, title: 'Refund Statement' }
        }));
        try {
            const templateId = button.dataset.templateId;
            const leaseId = button.dataset.leaseId;

            if (!templateId || !leaseId) {
                throw new Error('Refund template or lease ID is missing.');
            }

            const formData = new FormData();
            formData.set('template_id', templateId);
            formData.set('category', 'refund');
            formData.set('lease_id', leaseId);
            if (button.dataset.checkOutDate) {
                formData.set('check_out_date', button.dataset.checkOutDate);
            }

            const response = await fetch('{{ route('admin.leases.preview-template') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: formData
            });

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(error.message || 'Unable to generate refund preview.');
            }

            const rendered = await response.json();
            if (typeof rendered.content !== 'string') throw new Error('Unable to generate refund preview.');
            window.dispatchEvent(new CustomEvent('open-lease-preview', {
                detail: {
                    requestId,
                    content: rendered.content,
                    title: rendered.title
                }
            }));
        } catch (error) {
            window.dispatchEvent(new CustomEvent('lease-preview-error', {
                detail: { requestId, message: error.message || 'Unable to generate refund preview.' }
            }));
        }
    };

</script>
@endonce
