<?php
require_once 'includes/auth_check.php';
include 'header.php';
?>

<main id="app" class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-cyan-500/20 flex items-center justify-center">
                    <i class="fas fa-palette text-cyan-400"></i>
                </span>
                Menu &amp; Theme Customizer
            </h1>
            <p class="text-slate-400 text-sm mt-1">Manage dynamic sidebar/navbar navigation links and personalize system colors.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- ── COLUMN 1: Theme Settings Customizer ── -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-slate-800 border border-slate-700 rounded-xl shadow-xl p-6">
                <h2 class="text-lg font-semibold text-white mb-5 flex items-center gap-2">
                    <i class="fas fa-eye-dropper text-cyan-400"></i> Theme Colors
                </h2>

                <form id="themeForm" class="space-y-4">
                    <!-- Accent Color -->
                    <div>
                        <label for="theme_accent_color" class="block text-sm font-medium text-slate-300 mb-1">
                            Accent Color (Buttons, Highlights)
                        </label>
                        <div class="flex gap-2 items-center">
                            <input type="color" id="theme_accent_color" class="w-12 h-10 bg-slate-900 border border-slate-600 rounded-lg cursor-pointer p-1">
                            <input type="text" id="theme_accent_color_text" class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white font-mono text-sm focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="#06b6d4">
                        </div>
                    </div>

                    <!-- Navbar Background -->
                    <div>
                        <label for="theme_navbar_bg" class="block text-sm font-medium text-slate-300 mb-1">
                            Navbar/Sidebar Background
                        </label>
                        <div class="flex gap-2 items-center">
                            <input type="color" id="theme_navbar_bg" class="w-12 h-10 bg-slate-900 border border-slate-600 rounded-lg cursor-pointer p-1">
                            <input type="text" id="theme_navbar_bg_text" class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white font-mono text-sm focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="#0f172a">
                        </div>
                    </div>

                    <!-- Text Color -->
                    <div>
                        <label for="theme_text_color" class="block text-sm font-medium text-slate-300 mb-1">
                            Base Text Color
                        </label>
                        <div class="flex gap-2 items-center">
                            <input type="color" id="theme_text_color" class="w-12 h-10 bg-slate-900 border border-slate-600 rounded-lg cursor-pointer p-1">
                            <input type="text" id="theme_text_color_text" class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-white font-mono text-sm focus:ring-2 focus:ring-cyan-500 outline-none" placeholder="#cbd5e1">
                        </div>
                    </div>

                    <button type="submit" class="w-full px-4 py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg font-semibold transition-colors flex items-center justify-center gap-2 text-sm mt-4">
                        <i class="fas fa-save"></i> Save Colors
                    </button>
                </form>
            </div>
            
            <div class="bg-slate-800 border border-slate-700 rounded-xl shadow-xl p-6">
                <h3 class="text-sm font-semibold text-white mb-2 flex items-center gap-2">
                    <i class="fas fa-info-circle text-slate-400"></i> Info &amp; Instructions
                </h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-2">
                    Customizing colors updates the system navigation sidebar, buttons, links, and text formatting immediately for all active users.
                </p>
                <p class="text-xs text-slate-400 leading-relaxed">
                    For icons, use standard <a href="https://fontawesome.com/v6/search?o=r&m=free" target="_blank" class="text-cyan-400 hover:underline">FontAwesome Free classes</a> (e.g. <code>fas fa-server</code> or <code>fab fa-telegram</code>).
                </p>
            </div>
        </div>

        <!-- ── COLUMN 2: Menu Items Manager ── -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-slate-800 border border-slate-700 rounded-xl shadow-xl overflow-hidden">
                <div class="p-5 border-b border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-list text-slate-400"></i> Menu Tree Navigation
                    </h2>
                    <button onclick="openMenuModal()" class="px-4 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-semibold transition-colors flex items-center gap-2">
                        <i class="fas fa-plus"></i> Add Menu Item
                    </button>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-900/60">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Icon &amp; Title</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">URL Destination</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Parent Folder</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Sort</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Role</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="menuTableBody">
                            <!-- Loaded dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- ── Add/Edit Menu Modal ── -->
<div id="menuModal" class="fixed inset-0 bg-black/60 display-none items-center justify-center z-[100] backdrop-filter blur-[2px] hidden">
    <div class="bg-slate-800 rounded-xl shadow-2xl p-6 w-full max-w-md border border-slate-700 m-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
            <h2 id="modalTitle" class="text-lg font-semibold text-white flex items-center gap-2">
                <i class="fas fa-plus text-cyan-400"></i> Add Menu Item
            </h2>
            <button onclick="closeMenuModal()" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-slate-700 transition-colors text-lg">&times;</button>
        </div>
        <form id="menuForm" class="space-y-4">
            <input type="hidden" id="menu_id">

            <!-- Title -->
            <div>
                <label for="menu_title" class="block text-sm font-medium text-slate-300 mb-1">Title *</label>
                <input type="text" id="menu_title" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-cyan-500 outline-none text-sm" placeholder="e.g. Map">
            </div>

            <!-- URL -->
            <div>
                <label for="menu_url" class="block text-sm font-medium text-slate-300 mb-1">URL / Link *</label>
                <input type="text" id="menu_url" required class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-cyan-500 outline-none text-sm font-mono" placeholder="e.g. map.php (use '#' for parent folders)">
            </div>

            <!-- Icon -->
            <div>
                <label for="menu_icon" class="block text-sm font-medium text-slate-300 mb-1">FontAwesome Icon Class</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i id="icon_preview" class="fas fa-link text-xs"></i></span>
                    <input type="text" id="menu_icon" class="w-full pl-9 pr-4 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white focus:ring-2 focus:ring-cyan-500 outline-none text-sm font-mono" placeholder="e.g. fas fa-map" oninput="previewIcon(this.value)">
                </div>
            </div>

            <!-- Parent -->
            <div>
                <label for="menu_parent_id" class="block text-sm font-medium text-slate-300 mb-1">Parent Item (For Dropdowns)</label>
                <select id="menu_parent_id" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-cyan-500 outline-none text-sm">
                    <option value="">-- None (Top Level Link) --</option>
                </select>
            </div>

            <!-- Sort order -->
            <div>
                <label for="menu_sort_order" class="block text-sm font-medium text-slate-300 mb-1">Sort Order</label>
                <input type="number" id="menu_sort_order" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-cyan-500 outline-none text-sm" placeholder="0" value="0">
            </div>

            <!-- Role required -->
            <div>
                <label for="menu_role_required" class="block text-sm font-medium text-slate-300 mb-1">Role Required</label>
                <select id="menu_role_required" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-cyan-500 outline-none text-sm">
                    <option value="viewer">Viewer (All logged in users)</option>
                    <option value="admin">Admin Only</option>
                </select>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeMenuModal()" class="px-4 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600 transition-colors text-sm">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg font-semibold transition-colors text-sm">
                    <i class="fas fa-save mr-1"></i> Save Link
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initThemeSettings();
    initMenuManagement();
});

// Color Pickers sync
function syncColorPicker(pickerId, textId) {
    const picker = document.getElementById(pickerId);
    const text = document.getElementById(textId);
    if (!picker || !text) return;
    
    picker.addEventListener('input', () => { text.value = picker.value; });
    text.addEventListener('input', () => {
        if (/^#[0-9A-F]{6}$/i.test(text.value)) {
            picker.value = text.value;
        }
    });
}

function initThemeSettings() {
    syncColorPicker('theme_accent_color', 'theme_accent_color_text');
    syncColorPicker('theme_navbar_bg', 'theme_navbar_bg_text');
    syncColorPicker('theme_text_color', 'theme_text_color_text');

    // Load theme settings
    fetch('api.php?action=get_theme_settings')
        .then(res => res.json())
        .then(data => {
            if (data) {
                document.getElementById('theme_accent_color').value = data.theme_accent_color || '#06b6d4';
                document.getElementById('theme_accent_color_text').value = data.theme_accent_color || '#06b6d4';
                document.getElementById('theme_navbar_bg').value = data.theme_navbar_bg || '#0f172a';
                document.getElementById('theme_navbar_bg_text').value = data.theme_navbar_bg || '#0f172a';
                document.getElementById('theme_text_color').value = data.theme_text_color || '#cbd5e1';
                document.getElementById('theme_text_color_text').value = data.theme_text_color || '#cbd5e1';
            }
        })
        .catch(err => console.error('Failed to load theme settings:', err));

    // Save colors
    document.getElementById('themeForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const payload = {
            theme_accent_color: document.getElementById('theme_accent_color_text').value,
            theme_navbar_bg: document.getElementById('theme_navbar_bg_text').value,
            theme_text_color: document.getElementById('theme_text_color_text').value
        };

        fetch('api.php?action=save_theme_settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                window.notyf.success('Theme colors saved successfully. Please refresh the page to apply them.');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                window.notyf.error('Failed to save colors: ' + (res.error || 'Unknown error'));
            }
        })
        .catch(err => {
            window.notyf.error('Failed to save theme colors.');
            console.error(err);
        });
    });
}

let menuItems = [];

function initMenuManagement() {
    loadMenuItems();

    document.getElementById('menuForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const payload = {
            id: document.getElementById('menu_id').value || null,
            parent_id: document.getElementById('menu_parent_id').value || null,
            title: document.getElementById('menu_title').value,
            url: document.getElementById('menu_url').value,
            icon: document.getElementById('menu_icon').value,
            sort_order: document.getElementById('menu_sort_order').value,
            role_required: document.getElementById('menu_role_required').value
        };

        fetch('api.php?action=save_menu_item', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                window.notyf.success('Menu item saved successfully.');
                closeMenuModal();
                loadMenuItems();
            } else {
                window.notyf.error('Failed to save menu item: ' + (res.error || 'Unknown error'));
            }
        })
        .catch(err => {
            window.notyf.error('An error occurred while saving.');
            console.error(err);
        });
    });
}

function loadMenuItems() {
    fetch('api.php?action=get_menu_items')
        .then(res => res.json())
        .then(data => {
            menuItems = Array.isArray(data) ? data : [];
            renderMenuTable();
            populateParentsSelect();
        })
        .catch(err => {
            console.error('Failed to load menu items:', err);
        });
}

function renderMenuTable() {
    const tbody = document.getElementById('menuTableBody');
    if (menuItems.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No menu items found.</td></tr>`;
        return;
    }

    const parents = menuItems.filter(m => m.parent_id === null);
    const children = menuItems.filter(m => m.parent_id !== null);

    let html = '';

    parents.forEach(p => {
        html += renderRow(p, true);
        const sub = children.filter(c => Number(c.parent_id) === Number(p.id));
        sub.forEach(c => {
            html += renderRow(c, false, p.title);
        });
    });

    // Orphan items check (in case parent was deleted)
    const orphans = children.filter(c => !parents.some(p => Number(p.id) === Number(c.parent_id)));
    orphans.forEach(o => {
        html += renderRow(o, false, 'Unknown Parent (Orphan)');
    });

    tbody.innerHTML = html;
}

function renderRow(item, isParent, parentTitle = '') {
    const indent = isParent ? '' : 'pl-6 border-l-2 border-slate-700';
    const rowClass = isParent ? 'bg-slate-800/40 font-medium' : '';
    const parentDisplay = isParent ? '<span class="text-slate-500">—</span>' : `<span class="px-2 py-0.5 rounded bg-slate-700 text-slate-300 text-xs font-semibold">${parentTitle}</span>`;
    const roleBadge = item.role_required === 'admin'
        ? '<span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-950/60 text-red-300 border border-red-800/40">Admin</span>'
        : '<span class="px-2 py-0.5 rounded text-xs font-semibold bg-slate-700 text-slate-300 border border-slate-600">Viewer</span>';
    
    return `
        <tr class="border-b border-slate-700/50 hover:bg-slate-700/30 transition-colors ${rowClass}">
            <td class="px-4 py-3 whitespace-nowrap">
                <div class="flex items-center gap-2 ${indent}">
                    <span class="w-8 h-8 rounded-lg bg-slate-900 border border-slate-700 flex items-center justify-center text-slate-400">
                        <i class="${item.icon || 'fas fa-link'}"></i>
                    </span>
                    <span class="text-white">${item.title}</span>
                </div>
            </td>
            <td class="px-4 py-3 whitespace-nowrap font-mono text-xs text-slate-400">${item.url}</td>
            <td class="px-4 py-3 whitespace-nowrap">${parentDisplay}</td>
            <td class="px-4 py-3 whitespace-nowrap text-slate-300 font-mono text-xs">${item.sort_order}</td>
            <td class="px-4 py-3 whitespace-nowrap">${roleBadge}</td>
            <td class="px-4 py-3 whitespace-nowrap space-x-1">
                <button onclick="editMenuItem(${item.id})" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs rounded-md bg-yellow-900/40 text-yellow-300 border border-yellow-700/50 hover:bg-yellow-800/50 transition-colors">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button onclick="deleteMenuItem(${item.id}, '${item.title}')" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs rounded-md bg-red-900/30 text-red-400 border border-red-800/40 hover:bg-red-800/50 transition-colors">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </td>
        </tr>
    `;
}

function populateParentsSelect() {
    const select = document.getElementById('menu_parent_id');
    select.innerHTML = '<option value="">-- None (Top Level Link) --</option>';
    
    // Parents are items where parent_id is null
    const parents = menuItems.filter(m => m.parent_id === null);
    parents.forEach(p => {
        select.innerHTML += `<option value="${p.id}">${p.title}</option>`;
    });
}

function previewIcon(iconClass) {
    const iconPreview = document.getElementById('icon_preview');
    if (iconPreview) {
        iconPreview.className = (iconClass || 'fas fa-link') + ' text-xs';
    }
}

function openMenuModal() {
    document.getElementById('menuForm').reset();
    document.getElementById('menu_id').value = '';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus text-cyan-400"></i> Add Menu Item';
    previewIcon('fas fa-link');
    
    const modal = document.getElementById('menuModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeMenuModal() {
    const modal = document.getElementById('menuModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function editMenuItem(id) {
    const item = menuItems.find(m => Number(m.id) === Number(id));
    if (!item) return;

    document.getElementById('menu_id').value = item.id;
    document.getElementById('menu_parent_id').value = item.parent_id || '';
    document.getElementById('menu_title').value = item.title;
    document.getElementById('menu_url').value = item.url;
    document.getElementById('menu_icon').value = item.icon || '';
    document.getElementById('menu_sort_order').value = item.sort_order || 0;
    document.getElementById('menu_role_required').value = item.role_required || 'viewer';

    previewIcon(item.icon);
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit text-yellow-400"></i> Edit Menu Item';

    const modal = document.getElementById('menuModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function deleteMenuItem(id, title) {
    if (confirm(`Are you sure you want to delete menu item "${title}"? If it is a parent folder, all submenus inside it will also be deleted!`)) {
        fetch('api.php?action=delete_menu_item', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                window.notyf.success(`Menu item "${title}" deleted.`);
                loadMenuItems();
            } else {
                window.notyf.error('Failed to delete menu item.');
            }
        })
        .catch(err => {
            window.notyf.error('An error occurred during deletion.');
            console.error(err);
        });
    }
}
</script>

<?php include 'footer.php'; ?>
