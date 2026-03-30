<?php
require_once 'includes/auth_check.php';
include 'header.php';
?>
<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-white">Templates</h1>
            <div class="flex gap-2">
                <button id="newTemplateBtn" class="px-4 py-2 bg-cyan-600 text-white rounded hover:bg-cyan-500">New Template</button>
                <button id="exportTemplatesBtn" class="px-4 py-2 bg-slate-600 text-white rounded hover:bg-slate-500">Export</button>
                <input id="importTemplatesFile" type="file" class="hidden" accept=".json,.yaml,.yml">
                <button id="importTemplatesBtn" class="px-4 py-2 bg-slate-600 text-white rounded hover:bg-slate-500">Import</button>
            </div>
        </div>
        <div class="bg-slate-800 border border-slate-700 rounded-lg p-6">
            <table class="min-w-full">
                <thead><tr class="border-b border-slate-700">
                    <th class="px-3 py-2 text-left text-xs uppercase text-slate-400">Name</th>
                    <th class="px-3 py-2 text-left text-xs uppercase text-slate-400">Description</th>
                    <th class="px-3 py-2 text-left text-xs uppercase text-slate-400">Triggers</th>
                    <th class="px-3 py-2 text-left text-xs uppercase text-slate-400">Actions</th>
                </tr></thead>
                <tbody id="templatesTableBody"></tbody>
            </table>
        </div>
    </div>
</main>
<?php include 'footer.php'; ?>
