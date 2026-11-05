<style>
    /* ==========================================
       整个编辑器容器
       左：Blocks
       中：Canvas
       右：Traits + Styles
       ========================================== */

    #editor-wrapper {
        display: flex !important;
        flex-direction: column !important;

        width: 100% !important;
        height: 760px !important;

        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;

        overflow: hidden !important;

        box-sizing: border-box !important;
    }


    /* ==========================================
       Collapsible reusable blocks panel
       ========================================== */

    #blocks-panel {
        position: relative !important;
        width: 100% !important;
        flex: 0 0 auto !important;
        background: #3f3337;
        border-bottom: 1px solid #5b4a50;
        z-index: 10 !important;
    }

    #blocks-toggle {
        width: 100% !important;
        min-height: 46px !important;
        padding: 0 16px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        color: #f8fafc !important;
        background: transparent !important;
        border: 0 !important;
        cursor: pointer !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        letter-spacing: 0.04em !important;
        text-align: left !important;
    }

    #blocks-toggle:hover {
        background: #4a3b40 !important;
    }

    #blocks-toggle svg {
        width: 16px !important;
        height: 16px !important;
        transition: transform 150ms ease !important;
    }

    #blocks-toggle[aria-expanded="true"] svg {
        transform: rotate(180deg) !important;
    }

    #blocks-container {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        overflow: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }

    #blocks-category-nav {
        display: flex !important;
        align-items: stretch !important;
        gap: 1px !important;
        width: 100% !important;
        min-height: 42px !important;
        padding: 0 8px !important;
        overflow-x: auto !important;
        overflow-y: visible !important;
        background: #3f3337 !important;
        scrollbar-width: thin;
    }

    #blocks-category-nav button {
        flex: 0 0 auto !important;
        min-height: 42px !important;
        padding: 0 14px !important;
        border: 0 !important;
        border-bottom: 3px solid transparent !important;
        color: #e5e7eb !important;
        background: transparent !important;
        cursor: pointer !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        white-space: nowrap !important;
    }

    #blocks-category-nav button:hover,
    #blocks-category-nav button.is-active {
        color: #fff !important;
        background: #54444a !important;
        border-bottom-color: #f472b6 !important;
    }

    #blocks-dropdown-layer {
        position: absolute !important;
        top: 88px !important;
        left: 0 !important;
        right: 0 !important;
        z-index: 30 !important;
        pointer-events: none !important;
    }

    #blocks-dropdown-layer .block-category-menu {
        position: absolute !important;
        left: 0 !important;
        right: 0 !important;
        display: none !important;
        max-height: 220px !important;
        overflow-y: auto !important;
        padding: 8px !important;
        background: #fff !important;
        border: 1px solid #d1d5db !important;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.18) !important;
    }

    #blocks-dropdown-layer .block-category-menu.is-open {
        display: grid !important;
        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
        gap: 8px !important;
        pointer-events: auto !important;
    }

    /* Native categories remain mounted as GrapesJS block sources. */
    #blocks-container .gjs-block-category {
        display: block !important;
    }

    #blocks-container .gjs-block-category .gjs-title {
        display: none !important;
    }

    /* Compact reusable blocks inside an expanded category */
    #blocks-container .gjs-blocks-c {
        display: contents !important;
    }

    #blocks-dropdown-layer .gjs-block {
        width: 100% !important;
        min-height: 56px !important;
        height: 56px !important;
        margin: 0 !important;
        padding: 8px 10px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: 10px !important;
        box-sizing: border-box !important;
    }

    #blocks-dropdown-layer .gjs-block,
    #blocks-container .gjs-block {
        width: 100% !important;
        min-height: 56px !important;
        height: 56px !important;
        margin: 0 !important;
        padding: 8px 10px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: 10px !important;
        color: #fff !important;
        box-sizing: border-box !important;
    }

    #blocks-container .gjs-block:last-child {
        margin-bottom: 0 !important;
    }

    #blocks-dropdown-layer .gjs-block-label,
    #blocks-container .gjs-block-label {
        margin: 0 !important;
        color: #fff !important;
        font-size: 12px !important;
        line-height: 1.2 !important;
        text-align: left !important;
    }

    #blocks-dropdown-layer .gjs-block svg,
    #blocks-dropdown-layer .gjs-block i,
    #blocks-container .gjs-block svg,
    #blocks-container .gjs-block i {
        flex: 0 0 auto !important;
        max-width: 32px !important;
        max-height: 32px !important;
    }


    /* ==========================================
       Canvas and settings area
       ========================================== */

    #editor-main {
        flex: 1 1 auto !important;
        width: auto !important;
        min-width: 0 !important;
        min-height: 0 !important;
        display: flex !important;
        flex-direction: row !important;
        overflow: hidden !important;
        box-sizing: border-box !important;
    }

    #{{ $id ?? 'gjs' }} {
        flex: 1 1 auto !important;
        width: auto !important;
        min-width: 0 !important;
        height: 100% !important;
        position: relative;
        overflow: hidden !important;
        box-sizing: border-box !important;
    }


    /* ==========================================
       Fixed A4 GrapesJS Canvas
       ========================================== */

    .gjs-cv-canvas {
        background: #e5e7eb !important;
        overflow: auto !important;
    }

    .gjs-frame-wrapper {
        margin: 0 !important;
        transition: none !important;
    }

    .gjs-frame {
        width: 100% !important;
        height: 100% !important;
        display: block;
        margin: 0 !important;
        background: #fff !important;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.18);
    }

    .gjs-placeholder {
        border-color: #2563eb !important;
    }


    /* ==========================================
       右侧统一 Panel
       ========================================== */

    #right-panel {
        width: 250px !important;
        flex: 0 0 250px !important;

        height: 100% !important;
        min-height: 0 !important;

        overflow-x: hidden !important;
        overflow-y: auto !important;
        overscroll-behavior-y: contain;

        background: #f9fafb;

        border-left: 1px solid #e2e8f0;

        box-sizing: border-box !important;
    }


    /* ==========================================
       Traits
       ========================================== */

    #traits-container {
        width: 100% !important;

        background: #f9fafb;

        box-sizing: border-box !important;
    }


    /* ==========================================
       Styles
       ========================================== */

    #styles-container {
        width: 100% !important;

        background: #f9fafb;

        box-sizing: border-box !important;
    }


    /* ==========================================
       GrapesJS Traits - Select
       ========================================== */

    #traits-container .gjs-field select {
        color: #333 !important;

        background-color: #fff !important;

        border: 1px solid #cbd5e1 !important;
    }


    #traits-container .gjs-field select option {
        color: #333 !important;

        background-color: #fff !important;
    }


    #traits-container .gjs-trt-trait {
        color: #333 !important;
    }


    /* ==========================================
       OL Start Input
       ========================================== */

    #traits-container input[name="listStart"] {
        width: 100% !important;

        box-sizing: border-box !important;
    }

    /* ==========================================
   Traits 与 Styles 统一视觉
   ========================================== */

/* Trait 整体文字 */
#traits-container,
#traits-container .gjs-trt-trait,
#traits-container .gjs-trt-trait__label,
#traits-container label {
    color: #333 !important;
    font-size: 13px !important;
}


/* Trait 的 Select */
#traits-container .gjs-field select {
    color: #333 !important;
    background-color: #fff !important;
    border: 1px solid #cbd5e1 !important;

    font-size: 13px !important;
    font-family: inherit !important;

    height: 32px !important;

    box-sizing: border-box !important;
}


/* Trait Select 的 Option */
#traits-container .gjs-field select option {
    color: #333 !important;
    background-color: #fff !important;
}

/* 让 Traits 的 List Type / Start 文字颜色跟 Styles 一致 */
#traits-container .gjs-trt-trait,
#traits-container .gjs-trt-trait__label,
#traits-container .gjs-trt-trait label {
    color: #6b7280 !important;
}

/* ==========================================
   Start Input
   ========================================== */

#traits-container .gjs-field input {
    color: #333 !important;
    background-color: #fff !important;

    border: 1px solid #cbd5e1 !important;

    font-size: 13px !important;
    font-family: inherit !important;

    height: 32px !important;

    padding: 6px 8px !important;

    box-sizing: border-box !important;

    outline: none !important;
}


/* Start Input Focus */
#traits-container .gjs-field input:focus {
    border-color: #94a3b8 !important;

    background-color: #fff !important;

    color: #333 !important;

    outline: none !important;
}


/* ==========================================
   Trait Field 统一背景
   ========================================== */

#traits-container .gjs-field {
    color: #333 !important;
}


/* ==========================================
   Trait Row
   ========================================== */

#traits-container .gjs-trt-trait {
    background-color: transparent !important;
}
</style>

<div id="editor-wrapper" x-data="{ blocksOpen: true }">
    <div id="blocks-panel">
        <button id="blocks-toggle" type="button" @click="blocksOpen = !blocksOpen"
            :aria-expanded="blocksOpen.toString()">
            <span>Reusable Blocks</span>
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"></path>
            </svg>
        </button>
        <div id="blocks-category-nav" aria-label="Reusable block categories"></div>
        <div id="blocks-dropdown-layer" aria-live="polite"></div>
        <div id="blocks-container" :class="{ 'is-open': blocksOpen }"></div>
    </div>

    <div id="editor-main">
        <!-- 中间 Canvas -->
        <div id="{{ $id ?? 'gjs' }}"></div>

        <!-- 右侧统一 Panel -->
        <div id="right-panel">
            <div id="traits-container"></div>
            <div id="styles-container"></div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const editor = window.createAgreementEditor('{{ $id ?? "gjs" }}');
        window.dispatchEvent(
            new CustomEvent('editor-ready', {
                detail: editor
            })
        );
    });
</script>
