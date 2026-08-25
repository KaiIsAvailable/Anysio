<style>
    /* ==========================================
       整个编辑器容器
       左：Blocks
       中：Canvas
       右：Traits + Styles
       ========================================== */

    #editor-wrapper {
        display: flex !important;
        flex-direction: row !important;

        width: 100% !important;
        height: 600px !important;

        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;

        overflow: hidden !important;

        box-sizing: border-box !important;
    }


    /* ==========================================
       左侧 Blocks
       ========================================== */

    #blocks-container {
        width: 250px !important;
        flex: 0 0 250px !important;

        height: 100% !important;

        overflow-y: auto !important;
        overflow-x: hidden !important;

        background: #f9fafb;

        box-sizing: border-box !important;
    }


    /* ==========================================
       中间 Canvas
       ========================================== */

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
       GrapesJS Canvas Frame
       ========================================== */

    .gjs-frame {
        height: 100% !important;

        overflow-y: auto !important;
    }


    /* ==========================================
       右侧统一 Panel
       ========================================== */

    #right-panel {
        width: 250px !important;
        flex: 0 0 250px !important;

        height: 100% !important;

        overflow-y: auto !important;
        overflow-x: hidden !important;

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

<div id="editor-wrapper">

    <!-- 左侧 Blocks -->
    <div id="blocks-container"></div>

    <!-- 中间 Canvas -->
    <div id="{{ $id ?? 'gjs' }}"></div>

    <!-- 右侧统一 Panel -->
    <div id="right-panel">
        <div id="traits-container"></div>
        <div id="styles-container"></div>
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