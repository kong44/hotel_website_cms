<?php
/**
 * Indra Hotel - Centralized Media Library Manager CMS
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth();
$pdo = getDB();
$adminTitle = 'Media & Asset Library';

// Sync disk files with database on page load
Database::syncUploadsFolderToDatabase($pdo);

// Calculate Stats
$totalFiles = (int)$pdo->query("SELECT COUNT(*) FROM media_uploads")->fetchColumn();
$totalSize = (int)$pdo->query("SELECT SUM(file_size) FROM media_uploads")->fetchColumn();
$totalImages = (int)$pdo->query("SELECT COUNT(*) FROM media_uploads WHERE file_type = 'image'")->fetchColumn();
$totalVideos = (int)$pdo->query("SELECT COUNT(*) FROM media_uploads WHERE file_type = 'video'")->fetchColumn();

function format_bytes(int $bytes): string {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="space-y-6">

    <!-- Top Action & Info Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-stone-200">
        <div>
            <h2 class="font-headline text-2xl font-bold text-onyx-charcoal flex items-center gap-2">
                <span class="material-symbols-outlined text-2xl text-[#343c0a]">perm_media</span>
                <span>Media & Asset Library</span>
            </h2>
            <p class="text-xs text-stone-500 mt-1">Manage, upload, reuse, and delete media files across your hotel website.</p>
        </div>

        <div class="flex items-center gap-3">
            <button onclick="toggleMediaUploadZone()" class="bg-[#343c0a] hover:bg-deep-olive text-white px-4 py-2 rounded-lg text-xs font-bold transition shadow flex items-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-base">cloud_upload</span>
                <span>Upload New Media</span>
            </button>
        </div>
    </div>

    <!-- Stats Overview Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl border border-stone-200 shadow-2xs flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-lg bg-stone-100 text-[#343c0a] flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-xl">folder_zip</span>
            </div>
            <div>
                <div class="text-xs font-semibold text-stone-500 uppercase">Total Files</div>
                <div class="text-lg font-bold text-stone-900 leading-tight" id="stat-total-files"><?= number_format($totalFiles) ?></div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-stone-200 shadow-2xs flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-xl">hard_drive</span>
            </div>
            <div>
                <div class="text-xs font-semibold text-stone-500 uppercase">Storage Used</div>
                <div class="text-lg font-bold text-stone-900 leading-tight"><?= format_bytes($totalSize) ?></div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-stone-200 shadow-2xs flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-xl">image</span>
            </div>
            <div>
                <div class="text-xs font-semibold text-stone-500 uppercase">Images</div>
                <div class="text-lg font-bold text-stone-900 leading-tight"><?= number_format($totalImages) ?></div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-stone-200 shadow-2xs flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-lg bg-purple-50 text-purple-700 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-xl">movie</span>
            </div>
            <div>
                <div class="text-xs font-semibold text-stone-500 uppercase">Videos</div>
                <div class="text-lg font-bold text-stone-900 leading-tight"><?= number_format($totalVideos) ?></div>
            </div>
        </div>
    </div>

    <!-- Drag & Drop Upload Zone (Collapsible / Toggleable) -->
    <div id="media-upload-zone" class="bg-stone-900 text-white rounded-2xl p-6 shadow-lg border border-stone-800 hidden space-y-4">
        <div class="flex items-center justify-between border-b border-stone-800 pb-3">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[#dfe8a6]">cloud_upload</span>
                <h3 class="font-headline font-bold text-sm text-white">Upload Media Assets</h3>
            </div>
            <button onclick="toggleMediaUploadZone()" class="text-stone-400 hover:text-white transition">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-center">
            <div>
                <label class="block text-[11px] font-semibold text-stone-400 uppercase mb-1">Target Subfolder</label>
                <select id="upload-target-folder" class="w-full bg-stone-800 border border-stone-700 rounded-lg p-2 text-xs text-white">
                    <option value="general">general (Default)</option>
                    <option value="rooms">rooms</option>
                    <option value="dining">dining</option>
                    <option value="offers">offers</option>
                    <option value="gallery">gallery</option>
                    <option value="brand">brand</option>
                    <option value="videos">videos</option>
                </select>
            </div>

            <div class="sm:col-span-3">
                <label class="block text-[11px] font-semibold text-stone-400 uppercase mb-1">Select or Drop Files</label>
                <div id="drop-area" 
                     onclick="document.getElementById('media-file-input').click()" 
                     ondragover="handleDragOver(event)" 
                     ondragleave="handleDragLeave(event)" 
                     ondrop="handleDrop(event)"
                     class="border-2 border-dashed border-stone-700 hover:border-[#dfe8a6] rounded-xl p-6 text-center cursor-pointer transition bg-stone-950/40">
                    <span class="material-symbols-outlined text-3xl text-stone-500">add_photo_alternate</span>
                    <p class="text-xs text-stone-300 font-medium mt-1">Drag & drop files here or <span class="text-[#dfe8a6] underline font-bold">browse your computer</span></p>
                    <p class="text-[10px] text-stone-500 mt-1">Supports JPG, PNG, WEBP, SVG, MP4, WebM (Auto-chunked streaming up to 100MB+)</p>
                    <input type="file" id="media-file-input" multiple accept="image/*,video/*" class="hidden" onchange="handleMediaFilesSelected(this.files)">
                </div>
            </div>
        </div>

        <div id="upload-progress-list" class="space-y-2 hidden pt-2 border-t border-stone-800"></div>
    </div>

    <!-- Search & Filters Toolbar -->
    <div class="bg-white rounded-xl border border-stone-200 p-4 shadow-2xs space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            
            <!-- Type Tabs -->
            <div class="flex items-center gap-1 overflow-x-auto pb-1 md:pb-0 text-xs font-semibold">
                <button onclick="filterMediaType('all')" id="tab-type-all" class="media-type-tab active bg-[#343c0a] text-white px-3 py-1.5 rounded-lg transition cursor-pointer">All Files</button>
                <button onclick="filterMediaType('image')" id="tab-type-image" class="media-type-tab text-stone-600 hover:bg-stone-100 px-3 py-1.5 rounded-lg transition cursor-pointer">Images</button>
                <button onclick="filterMediaType('video')" id="tab-type-video" class="media-type-tab text-stone-600 hover:bg-stone-100 px-3 py-1.5 rounded-lg transition cursor-pointer">Videos</button>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <!-- Search Input -->
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-2.5 text-stone-400 text-base">search</span>
                    <input type="text" id="media-search-input" onkeyup="debounceMediaSearch()" placeholder="Search media name..." class="w-full sm:w-60 border border-stone-300 rounded-lg pl-9 pr-3 py-1.5 text-xs bg-stone-50 focus:bg-white focus:ring-2 focus:ring-[#343c0a]">
                </div>

                <!-- Folder Filter Dropdown -->
                <select id="media-folder-select" onchange="fetchMediaList()" class="border border-stone-300 rounded-lg px-3 py-1.5 text-xs bg-white">
                    <option value="all">All Folders</option>
                    <option value="general">general</option>
                    <option value="rooms">rooms</option>
                    <option value="dining">dining</option>
                    <option value="offers">offers</option>
                    <option value="gallery">gallery</option>
                    <option value="brand">brand</option>
                    <option value="videos">videos</option>
                </select>

                <!-- Refresh Button -->
                <button onclick="fetchMediaList()" class="p-2 border border-stone-300 rounded-lg text-stone-600 hover:bg-stone-100 transition cursor-pointer" title="Refresh Library">
                    <span class="material-symbols-outlined text-base">refresh</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Media Library Grid -->
    <div id="media-grid-container" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 min-h-[300px]">
        <!-- Dynamic Items rendered by JS -->
    </div>

    <!-- Pagination controls -->
    <div id="media-pagination" class="flex items-center justify-between pt-4 border-t border-stone-200 text-xs text-stone-500">
        <span id="pagination-info">Showing 0 of 0 files</span>
        <div class="flex items-center gap-2">
            <button id="btn-prev-page" onclick="changeMediaPage(-1)" disabled class="px-3 py-1.5 rounded border border-stone-300 bg-white disabled:opacity-50 font-semibold cursor-pointer">Previous</button>
            <span id="pagination-current-page" class="font-bold text-stone-800">1</span>
            <button id="btn-next-page" onclick="changeMediaPage(1)" disabled class="px-3 py-1.5 rounded border border-stone-300 bg-white disabled:opacity-50 font-semibold cursor-pointer">Next</button>
        </div>
    </div>

</div>

<!-- Delete Confirmation Modal -->
<div id="delete-media-modal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 border border-stone-200 shadow-2xl space-y-4 text-center">
        <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto">
            <span class="material-symbols-outlined text-2xl">delete_forever</span>
        </div>
        <div>
            <h3 class="font-headline font-bold text-base text-stone-900">Delete Media File?</h3>
            <p class="text-xs text-stone-500 mt-1">This will permanently delete the file from the server storage and database. This action cannot be undone.</p>
        </div>
        <input type="hidden" id="delete-media-id" value="">
        <div class="flex justify-center gap-3 pt-2">
            <button onclick="closeDeleteMediaModal()" class="px-4 py-2 border border-stone-300 rounded-lg text-stone-600 text-xs font-semibold cursor-pointer">Cancel</button>
            <button onclick="confirmDeleteMedia()" class="bg-rose-600 hover:bg-rose-700 text-white px-5 py-2 rounded-lg text-xs font-bold shadow cursor-pointer">Delete Permanently</button>
        </div>
    </div>
</div>

<script>
let currentMediaType = 'all';
let currentMediaFolder = 'all';
let currentMediaSearch = '';
let currentMediaPage = 1;
let searchDebounceTimer = null;

document.addEventListener('DOMContentLoaded', () => {
    fetchMediaList();
});

function toggleMediaUploadZone() {
    const zone = document.getElementById('media-upload-zone');
    zone.classList.toggle('hidden');
}

function filterMediaType(type) {
    currentMediaType = type;
    currentMediaPage = 1;
    document.querySelectorAll('.media-type-tab').forEach(btn => {
        btn.classList.remove('bg-[#343c0a]', 'text-white');
        btn.classList.add('text-stone-600', 'hover:bg-stone-100');
    });
    const activeTab = document.getElementById('tab-type-' + type);
    if (activeTab) {
        activeTab.classList.remove('text-stone-600', 'hover:bg-stone-100');
        activeTab.classList.add('bg-[#343c0a]', 'text-white');
    }
    fetchMediaList();
}

function debounceMediaSearch() {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        currentMediaSearch = document.getElementById('media-search-input').value;
        currentMediaPage = 1;
        fetchMediaList();
    }, 300);
}

function changeMediaPage(delta) {
    currentMediaPage += delta;
    if (currentMediaPage < 1) currentMediaPage = 1;
    fetchMediaList();
}

async function fetchMediaList() {
    const container = document.getElementById('media-grid-container');
    const folderSelect = document.getElementById('media-folder-select');
    currentMediaFolder = folderSelect ? folderSelect.value : 'all';

    container.innerHTML = `
        <div class="col-span-full py-12 text-center text-stone-400">
            <span class="material-symbols-outlined text-4xl animate-spin">autorenew</span>
            <p class="text-xs font-semibold mt-2">Loading media assets...</p>
        </div>
    `;

    try {
        const baseUrl = window.getApiEndpoint ? window.getApiEndpoint('') : '';
        const url = `${baseUrl}/api/media-api.php?action=list&folder=${encodeURIComponent(currentMediaFolder)}&type=${encodeURIComponent(currentMediaType)}&q=${encodeURIComponent(currentMediaSearch)}&page=${currentMediaPage}&limit=24`;
        const res = await fetch(url);
        const data = await res.json();

        if (data.success) {
            renderMediaGrid(data.data);
            updatePagination(data.meta);
        } else {
            container.innerHTML = `<div class="col-span-full py-8 text-center text-rose-500 text-xs font-bold">${data.error || 'Failed to load media'}</div>`;
        }
    } catch (e) {
        container.innerHTML = `<div class="col-span-full py-8 text-center text-rose-500 text-xs font-bold">Error connecting to server.</div>`;
    }
}

function renderMediaGrid(items) {
    const container = document.getElementById('media-grid-container');
    if (!items || items.length === 0) {
        container.innerHTML = `
            <div class="col-span-full py-16 text-center text-stone-400 bg-white rounded-xl border border-stone-200">
                <span class="material-symbols-outlined text-5xl">photo_library</span>
                <p class="text-xs font-bold text-stone-700 mt-2">No media files found</p>
                <p class="text-[11px] text-stone-400 mt-1">Try clearing filters or upload new images & videos.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = items.map(item => {
        const isVideo = item.file_type === 'video';
        const preview = isVideo 
            ? `<div class="w-full h-32 bg-stone-950 relative flex items-center justify-center group-hover:scale-105 transition duration-300">
                 <video src="${item.url}" class="w-full h-full object-cover opacity-80" muted preload="metadata"></video>
                 <span class="material-symbols-outlined text-3xl text-white/90 absolute">play_circle</span>
               </div>`
            : `<div class="w-full h-32 bg-stone-100 overflow-hidden relative group-hover:scale-105 transition duration-300">
                 <img src="${item.url}" alt="${item.filename}" class="w-full h-full object-cover" loading="lazy" onerror="this.src='${window.BASE_URL}/assets/images/softbook_logo.png'">
               </div>`;

        return `
            <div class="bg-white rounded-xl border border-stone-200 shadow-2xs overflow-hidden flex flex-col justify-between group relative transition hover:border-stone-400">
                <div class="relative">
                    ${preview}
                    <span class="absolute top-2 left-2 bg-stone-900/80 backdrop-blur-xs text-white text-[9px] font-bold uppercase px-2 py-0.5 rounded">
                        ${item.folder}
                    </span>
                </div>

                <div class="p-2.5 space-y-1">
                    <p class="text-xs font-bold text-stone-800 truncate" title="${item.filename}">${item.filename}</p>
                    <div class="flex items-center justify-between text-[10px] text-stone-400 font-medium">
                        <span>${item.formatted_size}</span>
                        <span>${item.formatted_date}</span>
                    </div>
                </div>

                <div class="p-2 pt-0 border-t border-stone-100 flex items-center justify-between gap-1 text-[11px]">
                    <button onclick="copyToClipboard('${item.url}')" class="flex-1 text-center py-1 text-stone-600 hover:text-[#343c0a] hover:bg-stone-100 rounded font-semibold transition" title="Copy Direct URL">
                        Copy Link
                    </button>
                    <a href="${item.url}" target="_blank" class="p-1 text-stone-400 hover:text-stone-900 rounded" title="View Fullsize">
                        <span class="material-symbols-outlined text-sm">open_in_new</span>
                    </a>
                    <button onclick="openDeleteMediaModal(${item.id})" class="p-1 text-rose-500 hover:bg-rose-50 rounded" title="Delete File">
                        <span class="material-symbols-outlined text-sm">delete</span>
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

function updatePagination(meta) {
    document.getElementById('pagination-info').textContent = `Showing page ${meta.page} of ${meta.total_pages || 1} (${meta.total} items)`;
    document.getElementById('pagination-current-page').textContent = meta.page;
    document.getElementById('btn-prev-page').disabled = meta.page <= 1;
    document.getElementById('btn-next-page').disabled = meta.page >= meta.total_pages;
    document.getElementById('stat-total-files').textContent = meta.total;
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Media URL copied to clipboard!');
    }).catch(() => {
        prompt('Copy media URL:', text);
    });
}

function openDeleteMediaModal(id) {
    document.getElementById('delete-media-id').value = id;
    document.getElementById('delete-media-modal').classList.remove('hidden');
}

function closeDeleteMediaModal() {
    document.getElementById('delete-media-modal').classList.add('hidden');
}

async function confirmDeleteMedia() {
    const id = document.getElementById('delete-media-id').value;
    if (!id) return;

    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        const baseUrl = window.getApiEndpoint ? window.getApiEndpoint('') : '';
        const res = await fetch(`${baseUrl}/api/media-api.php`, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        closeDeleteMediaModal();
        if (data.success) {
            fetchMediaList();
        } else if (data.in_use && data.usages) {
            openMediaInUseModal(data.usages);
        } else {
            alert('Failed to delete media: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        closeDeleteMediaModal();
        alert('Error deleting file: ' + e.message);
    }
}

// Drag & Drop Handlers
function handleDragOver(e) {
    e.preventDefault();
    document.getElementById('drop-area').classList.add('border-[#dfe8a6]', 'bg-stone-900');
}

function handleDragLeave(e) {
    e.preventDefault();
    document.getElementById('drop-area').classList.remove('border-[#dfe8a6]', 'bg-stone-900');
}

function handleDrop(e) {
    e.preventDefault();
    document.getElementById('drop-area').classList.remove('border-[#dfe8a6]', 'bg-stone-900');
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        handleMediaFilesSelected(e.dataTransfer.files);
    }
}

async function handleMediaFilesSelected(files) {
    if (!files || files.length === 0) return;
    const progressList = document.getElementById('upload-progress-list');
    const folder = document.getElementById('upload-target-folder').value || 'general';

    progressList.classList.remove('hidden');
    progressList.innerHTML = '';

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const itemDiv = document.createElement('div');
        itemDiv.className = 'flex items-center justify-between bg-stone-800 text-xs p-2 rounded border border-stone-700';
        itemDiv.innerHTML = `
            <div class="truncate max-w-xs text-stone-200">${file.name}</div>
            <div class="text-[10px] font-bold text-[#dfe8a6]" id="progress-item-${i}">Uploading...</div>
        `;
        progressList.appendChild(itemDiv);

        try {
            if (file.size > 1.5 * 1024 * 1024) {
                await uploadMediaChunked(file, folder, (pct, msg) => {
                    document.getElementById(`progress-item-${i}`).textContent = msg;
                });
            } else {
                const formData = new FormData();
                formData.append('action', 'upload');
                formData.append('file', file);
                formData.append('folder', folder);

                const baseUrl = window.getApiEndpoint ? window.getApiEndpoint('') : '';
                const res = await fetch(`${baseUrl}/api/media-api.php`, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'Upload error');
            }
            document.getElementById(`progress-item-${i}`).textContent = 'Completed';
            document.getElementById(`progress-item-${i}`).classList.replace('text-[#dfe8a6]', 'text-emerald-400');
        } catch (err) {
            document.getElementById(`progress-item-${i}`).textContent = 'Failed: ' + err.message;
            document.getElementById(`progress-item-${i}`).classList.replace('text-[#dfe8a6]', 'text-rose-400');
        }
    }

    fetchMediaList();
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
