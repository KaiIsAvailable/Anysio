<style>
    /* 容器：固定高度 */
    #editor-wrapper { 
        display: flex !important;
        flex-direction: row !important;
        height: 600px !important; 
        border: 1px solid #e2e8f0; 
        border-radius: 8px; 
        background: #fff;
        overflow: hidden; /* 这里隐藏是为了不让整个页面滚动 */
    }

    /* 侧边栏：保持不变 */
    #blocks-container, #styles-container { 
        width: 250px !important; 
        flex: 0 0 250px !important; 
        overflow-y: auto !important; 
        background: #f9fafb;
    }

    /* 中间画布容器 */
    #{{ $id ?? 'gjs' }} { 
        flex: 1 !important; 
        position: relative;
        height: 100% !important;
        overflow: hidden !important; /* 不要在外层 div 滚动 */
    }

    /* 关键修正：针对 GrapesJS 内部 iframe 的 wrapper */
    .gjs-frame {
        height: 100% !important;
        overflow-y: auto !important; /* 让 iframe 内容本身可滚动 */
    }
</style>

<div id="editor-wrapper">
    <div id="blocks-container"></div>
    <div id="{{ $id ?? 'gjs' }}"></div>
    <div id="styles-container"></div>
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