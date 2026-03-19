/**
 * SIDEBAR - Menu Lateral Retrátil com Submenu
 * Estratégia Nerd Admin v2.2
 */

(function() {
    'use strict';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        console.log('Sidebar JS inicializado');
        
        const sidebar = document.getElementById('sidebar');
        const mainWrapper = document.getElementById('mainWrapper');
        const overlay = document.getElementById('sidebarOverlay');

        if (!sidebar || !mainWrapper) {
            console.error('Elementos da sidebar não encontrados');
            return;
        }

        const STORAGE_KEYS = {
            COLLAPSED: 'sidebarCollapsed',
            ACTIVE_GROUP: 'sidebarActiveGroup'
        };

        function loadState() {
            const isCollapsed = localStorage.getItem(STORAGE_KEYS.COLLAPSED) === 'true';
            const activeGroup = localStorage.getItem(STORAGE_KEYS.ACTIVE_GROUP) || 'conteudo';

            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                mainWrapper.classList.add('sidebar-collapsed');
            }

            if (!isCollapsed && activeGroup) {
                setActiveGroup(activeGroup);
            }
        }

        function setActiveGroup(groupName) {
            document.querySelectorAll('.sidebar-group').forEach(group => {
                if (group.dataset.group === groupName) {
                    group.classList.add('active');
                } else {
                    group.classList.remove('active');
                }
            });
        }

        function toggleSidebar() {
            console.log('Toggle sidebar executado');
            
            const willCollapse = !sidebar.classList.contains('collapsed');

            sidebar.classList.toggle('collapsed');
            mainWrapper.classList.toggle('sidebar-collapsed');

            localStorage.setItem(STORAGE_KEYS.COLLAPSED, willCollapse);

            if (willCollapse) {
                document.querySelectorAll('.sidebar-group').forEach(g => g.classList.remove('active'));
            } else {
                const savedGroup = localStorage.getItem(STORAGE_KEYS.ACTIVE_GROUP);
                if (savedGroup) {
                    setActiveGroup(savedGroup);
                }
            }
        }

        function toggleGroup(groupName) {
            if (sidebar.classList.contains('collapsed')) return;

            const group = document.querySelector(`[data-group="${groupName}"]`);
            if (!group) return;

            const isActive = group.classList.contains('active');

            document.querySelectorAll('.sidebar-group').forEach(g => {
                g.classList.remove('active');
            });

            if (!isActive) {
                group.classList.add('active');
                localStorage.setItem(STORAGE_KEYS.ACTIVE_GROUP, groupName);
            } else {
                localStorage.removeItem(STORAGE_KEYS.ACTIVE_GROUP);
            }
        }

        function toggleMobileMenu() {
            const isOpen = sidebar.classList.toggle('mobile-open');
            if (overlay) overlay.classList.toggle('active');
            document.body.style.overflow = isOpen ? 'hidden' : '';
        }

        function closeMobileMenu() {
            sidebar.classList.remove('mobile-open');
            if (overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        // ===== EVENTOS =====
        
        // Botão toggle desktop
        const toggleBtn = document.querySelector('.sidebar-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }

        // Botão mobile
        const mobileBtn = document.querySelector('.mobile-menu-btn');
        if (mobileBtn) {
            mobileBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleMobileMenu();
            });
        }

        // Overlay
        if (overlay) {
            overlay.addEventListener('click', closeMobileMenu);
        }

        // Headers dos grupos - event delegation
        const sidebarNav = document.querySelector('.sidebar-nav');
        if (sidebarNav) {
            sidebarNav.addEventListener('click', function(e) {
                const header = e.target.closest('.sidebar-group-header');
                if (!header) return;
                
                const group = header.closest('.sidebar-group');
                if (!group) return;
                
                e.preventDefault();
                e.stopPropagation();
                toggleGroup(group.dataset.group);
            });
        }

        // Links do menu (fecha mobile ao clicar)
        document.querySelectorAll('.sidebar-item, .sidebar-floating-item, .sidebar-dashboard').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 1024) {
                    closeMobileMenu();
                }
            });
        });

        // Resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 1024) {
                closeMobileMenu();
            }
        });

        // Tecla ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
                closeMobileMenu();
            }
        });

        loadState();

        console.log('Sidebar JS carregado com sucesso');
    }
})();