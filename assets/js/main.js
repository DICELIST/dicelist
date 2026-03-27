/**
 * TRPG Bot 导航 - 主要 JavaScript
 */

// ======== 汉堡菜单 ========
(function() {
    const btn = document.getElementById('hamburgerBtn');
    const nav = document.getElementById('mainNav');
    if (btn && nav) {
        btn.addEventListener('click', function() {
            nav.classList.toggle('open');
        });
        document.addEventListener('click', function(e) {
            if (!btn.contains(e.target) && !nav.contains(e.target)) {
                nav.classList.remove('open');
            }
        });
    }
})();

// ======== Markdown 实时预览 ========
function initMarkdownPreview(textareaId, previewId) {
    const textarea = document.getElementById(textareaId);
    const preview  = document.getElementById(previewId);
    if (!textarea || !preview) return;

    function render() {
        const md = textarea.value;
        if (typeof marked !== 'undefined') {
            preview.innerHTML = marked.parse(md);
            // XSS 清理
            if (typeof DOMPurify !== 'undefined') {
                preview.innerHTML = DOMPurify.sanitize(preview.innerHTML);
            }
        } else {
            preview.textContent = md;
        }
    }

    textarea.addEventListener('input', render);
    render();
}

// ======== 确认删除 ========
function confirmDelete(msg) {
    return confirm(msg || '确定要删除吗？此操作不可撤销。');
}

// ======== Flash 消息自动关闭 ========
(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(el) {
        setTimeout(function() {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(function() { el.remove(); }, 500);
        }, 4000);
    });
})();

// ======== 管理后台侧栏高亮 ========
(function() {
    const currentPath = window.location.pathname;
    const adminLinks = document.querySelectorAll('.admin-nav a');
    adminLinks.forEach(function(link) {
        if (link.getAttribute('href') === currentPath) {
            link.classList.add('active');
        }
    });
})();

// ======== 搜索回车提交 ========
(function() {
    const searchInput = document.getElementById('searchKeyword');
    if (searchInput) {
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const form = searchInput.closest('form');
                if (form) form.submit();
            }
        });
    }
})();

// ======== 管理员下拉排序（拖拽提示） ========
// ======== 表格行操作按钮确认 ========
document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-confirm]');
    if (btn) {
        const msg = btn.getAttribute('data-confirm');
        if (!confirm(msg)) {
            e.preventDefault();
            e.stopPropagation();
        }
    }
});
