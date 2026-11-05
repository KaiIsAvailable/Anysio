<style>
    @media print {
        .pdf-page-break {
            display: block !important;
            break-before: page !important;
            page-break-before: always !important;
            border: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            height: 0 !important;
            min-height: 0 !important;
            line-height: 0 !important;
            font-size: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
        }

        .pdf-page-break > *,
        .pdf-page-break::before,
        .pdf-page-break::after {
            display: none !important;
        }
    }
</style>
