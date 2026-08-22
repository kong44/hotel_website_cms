/**
 * Indra Hotel - Universal Media & Video Uploader Client-Side Handler with Chunked Streaming & Progress
 */

window.getApiEndpoint = function(path) {
    if (!path) return '';
    let endpoint = path.startsWith('/') ? path : '/' + path;
    if (window.BASE_URL) {
        let base = window.BASE_URL.replace(/\/+$/, '');
        if (window.location.protocol === 'https:' && base.startsWith('http:')) {
            base = base.replace(/^http:/, 'https:');
        }
        endpoint = base + endpoint;
    }
    if (window.location.protocol === 'https:' && endpoint.startsWith('http:')) {
        endpoint = endpoint.replace(/^http:/, 'https:');
    }
    return endpoint;
};

window.ensureHttpsUrl = function(url) {
    if (!url) return url;
    if (window.location.protocol === 'https:' && typeof url === 'string' && url.startsWith('http:')) {
        return url.replace(/^http:/, 'https:');
    }
    return url;
};

// =======================================================
// Universal Chunked File Upload Engine (Supports 100MB+ Videos)
// =======================================================
async function uploadMediaChunked(file, folder, onProgress) {
    const CHUNK_SIZE = 1.5 * 1024 * 1024; // 1.5 MB slices (safe for any 2MB PHP server limit)
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    const fileUuid = 'upload_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);

    for (let i = 0; i < totalChunks; i++) {
        const start = i * CHUNK_SIZE;
        const end = Math.min(file.size, start + CHUNK_SIZE);
        const chunkBlob = file.slice(start, end);

        const formData = new FormData();
        formData.append('chunk', chunkBlob, file.name);
        formData.append('chunk_index', i);
        formData.append('total_chunks', totalChunks);
        formData.append('file_uuid', fileUuid);
        formData.append('filename', file.name);
        formData.append('folder', folder || 'general');

        if (onProgress) {
            const percent = Math.min(99, Math.round(((i) / totalChunks) * 100));
            onProgress(percent, 'Uploading... ' + percent + '%');
        }

        const response = await fetch(window.getApiEndpoint('/api/upload.php'), {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            const errData = await response.json().catch(() => ({ error: 'HTTP Error ' + response.status }));
            throw new Error(errData.error || 'Server error on chunk ' + (i + 1));
        }

        const data = await response.json();
        if (i === totalChunks - 1) {
            if (onProgress) onProgress(100, 'Processing... 100%');
            return data;
        }
    }
}

// =======================================================
// 1. Image Uploader Handlers
// =======================================================
window.handleImageFileSelect = async function (fileInput, widgetId, folder) {
    if (!fileInput.files || fileInput.files.length === 0) return;

    const file = fileInput.files[0];
    const previewImg = document.getElementById(widgetId + '_preview');
    const placeholder = document.getElementById(widgetId + '_placeholder');
    const progressBar = document.getElementById(widgetId + '_progress');
    const progressText = progressBar ? progressBar.querySelector('span:last-child') : null;
    const textInput = document.getElementById(widgetId + '_input');

    // Show progress spinner
    if (progressBar) progressBar.classList.remove('hidden');
    if (progressText) progressText.textContent = 'Uploading...';

    try {
        let data;
        if (file.size > 1.5 * 1024 * 1024) {
            data = await uploadMediaChunked(file, folder || 'general', (pct, msg) => {
                if (progressText) progressText.textContent = msg;
            });
        } else {
            const formData = new FormData();
            formData.append('image', file);
            formData.append('folder', folder || 'general');

            const response = await fetch(window.getApiEndpoint('/api/upload.php'), {
                method: 'POST',
                body: formData
            });
            data = await response.json();
        }

        if (progressBar) progressBar.classList.add('hidden');

        if (data && data.success && data.url) {
            const finalUrl = window.ensureHttpsUrl(data.url);
            if (textInput) {
                textInput.value = finalUrl;
                textInput.dispatchEvent(new Event('input', { bubbles: true }));
                textInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (previewImg) {
                previewImg.src = finalUrl;
                previewImg.classList.remove('hidden');
            }
            if (placeholder) {
                placeholder.classList.add('hidden');
            }
        } else {
            alert('Image upload failed: ' + ((data && data.error) ? data.error : 'Unknown server error'));
        }
    } catch (err) {
        if (progressBar) progressBar.classList.add('hidden');
        alert('Error while uploading image: ' + err.message);
    }

    // Reset file input
    fileInput.value = '';
};

// Helper function to robustly locate uploader widget DOM elements by widgetId or input name
function resolveWidgetElements(widgetId) {
    let textInput = document.getElementById(widgetId + '_input');
    let previewImg = document.getElementById(widgetId + '_preview');
    let placeholder = document.getElementById(widgetId + '_placeholder');
    let progressBar = document.getElementById(widgetId + '_progress');

    if (!textInput || !previewImg) {
        let input = document.querySelector(`input[name="${widgetId}"]`);
        if (!input) {
            input = document.querySelector(`input[id*="${widgetId}_input"]`);
        }
        if (input) {
            textInput = input;
            const widget = input.closest('.image-uploader-widget, .video-uploader-widget');
            if (widget) {
                if (!previewImg) previewImg = widget.querySelector('.image-preview-thumb, video, .video-preview-player');
                if (!placeholder) placeholder = widget.querySelector('.no-image-placeholder, .no-video-placeholder');
                if (!progressBar) progressBar = widget.querySelector('.upload-progress-bar');
            }
        }
    }

    return { textInput, previewImg, placeholder, progressBar };
}

window.handleImageUrlInput = function (url, widgetId) {
    const { textInput, previewImg, placeholder } = resolveWidgetElements(widgetId);

    const trimmed = (url || '').trim();
    if (textInput && textInput.value !== trimmed) {
        textInput.value = trimmed;
    }

    if (trimmed.length > 4) {
        if (previewImg) {
            previewImg.src = trimmed;
            previewImg.classList.remove('hidden');
        }
        if (placeholder) {
            placeholder.classList.add('hidden');
        }
    } else {
        if (previewImg) {
            previewImg.src = '';
            previewImg.classList.add('hidden');
        }
        if (placeholder) {
            placeholder.classList.remove('hidden');
        }
    }
};

window.clearImageUpload = function (widgetId) {
    const { textInput, previewImg, placeholder } = resolveWidgetElements(widgetId);

    if (textInput) {
        textInput.value = '';
        textInput.dispatchEvent(new Event('input', { bubbles: true }));
        textInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
    if (previewImg) {
        previewImg.src = '';
        previewImg.classList.add('hidden');
    }
    if (placeholder) {
        placeholder.classList.remove('hidden');
    }
};

// =======================================================
// 2. Video Background Uploader & Player Preview Handlers
// =======================================================
window.handleVideoFileSelect = async function (fileInput, widgetId, folder) {
    if (!fileInput.files || fileInput.files.length === 0) return;

    const file = fileInput.files[0];
    const { textInput, previewImg: previewVideo, placeholder, progressBar } = resolveWidgetElements(widgetId);
    const progressText = progressBar ? progressBar.querySelector('span:last-child') : null;

    // Show progress overlay
    if (progressBar) progressBar.classList.remove('hidden');
    if (progressText) progressText.textContent = 'Uploading Video... 0%';

    try {
        let data;
        if (file.size > 1.5 * 1024 * 1024) {
            data = await uploadMediaChunked(file, folder || 'videos', (pct, msg) => {
                if (progressText) progressText.textContent = msg;
            });
        } else {
            const formData = new FormData();
            formData.append('video', file);
            formData.append('folder', folder || 'videos');

            const response = await fetch(window.getApiEndpoint('/api/upload.php'), {
                method: 'POST',
                body: formData
            });
            data = await response.json();
        }

        if (progressBar) progressBar.classList.add('hidden');

        if (data && data.success && data.url) {
            const finalUrl = window.ensureHttpsUrl(data.url);
            if (textInput) {
                textInput.value = finalUrl;
                textInput.dispatchEvent(new Event('input', { bubbles: true }));
                textInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (previewVideo) {
                previewVideo.src = finalUrl;
                previewVideo.classList.remove('hidden');
                try { previewVideo.load(); } catch(e){}
            }
            if (placeholder) {
                placeholder.classList.add('hidden');
            }
        } else {
            alert('Video upload failed: ' + ((data && data.error) ? data.error : 'Unknown server error'));
        }
    } catch (err) {
        if (progressBar) progressBar.classList.add('hidden');
        alert('Error while uploading video: ' + err.message);
    }

    // Reset file input
    fileInput.value = '';
};

window.handleVideoUrlInput = function (url, widgetId) {
    const { textInput, previewImg: previewVideo, placeholder } = resolveWidgetElements(widgetId);

    const trimmed = (url || '').trim();
    if (textInput && textInput.value !== trimmed) {
        textInput.value = trimmed;
    }

    if (trimmed.length > 4) {
        if (previewVideo) {
            previewVideo.src = trimmed;
            previewVideo.classList.remove('hidden');
            try { previewVideo.load(); } catch(e){}
        }
        if (placeholder) {
            placeholder.classList.add('hidden');
        }
    } else {
        if (previewVideo) {
            previewVideo.src = '';
            previewVideo.classList.add('hidden');
        }
        if (placeholder) {
            placeholder.classList.remove('hidden');
        }
    }
};

window.clearVideoUpload = function (widgetId) {
    const { textInput, previewImg: previewVideo, placeholder } = resolveWidgetElements(widgetId);

    if (textInput) {
        textInput.value = '';
        textInput.dispatchEvent(new Event('input', { bubbles: true }));
        textInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
    if (previewVideo) {
        previewVideo.src = '';
        previewVideo.classList.add('hidden');
    }
    if (placeholder) {
        placeholder.classList.remove('hidden');
    }
};

// =======================================================
// 3. Global Reusable Media Library Picker Modal Engine
// =======================================================
let activePickerWidgetId = null;
let activePickerMediaType = 'all';
let activePickerFolder = 'all';
let activePickerSearch = '';
let activePickerPage = 1;
let pickerSearchTimer = null;

window.openMediaLibraryPicker = function(widgetId, filterType = 'all', defaultFolder = 'all') {
    activePickerWidgetId = widgetId;
    activePickerMediaType = filterType || 'all';
    activePickerFolder = 'all';
    activePickerSearch = '';
    activePickerPage = 1;

    ensureMediaPickerModalDOM();

    const modal = document.getElementById('media-picker-modal');
    if (modal) {
        modal.classList.remove('hidden');
        fetchPickerMediaList();
    }
};

window.closeMediaPickerModal = function() {
    const modal = document.getElementById('media-picker-modal');
    if (modal) modal.classList.add('hidden');
};

function ensureMediaPickerModalDOM() {
    if (document.getElementById('media-picker-modal')) return;

    const modalHTML = `
    <div id="media-picker-modal" class="fixed inset-0 bg-black/70 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-4xl w-full h-[85vh] flex flex-col border border-stone-200 shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-stone-200 flex items-center justify-between bg-stone-50">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-2xl text-[#343c0a]">photo_library</span>
                    <div>
                        <h3 class="font-headline font-bold text-base text-onyx-charcoal">Select Media from Library</h3>
                        <p class="text-[11px] text-stone-500">Choose an existing image or video to use in your form</p>
                    </div>
                </div>
                <button type="button" onclick="closeMediaPickerModal()" class="text-stone-400 hover:text-stone-700 transition">
                    <span class="material-symbols-outlined text-2xl">close</span>
                </button>
            </div>

            <!-- Toolbar -->
            <div class="px-6 py-3 border-b border-stone-200 flex flex-col sm:flex-row items-center justify-between gap-3 bg-white text-xs">
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <input type="text" id="picker-search-input" onkeyup="onPickerSearchInput()" placeholder="Search media..." class="border border-stone-300 rounded-lg px-3 py-1.5 text-xs w-full sm:w-56 bg-stone-50 focus:bg-white">
                    <select id="picker-folder-select" onchange="onPickerFolderChange()" class="border border-stone-300 rounded-lg px-3 py-1.5 text-xs">
                        <option value="all">All Folders</option>
                        <option value="general">general</option>
                        <option value="rooms">rooms</option>
                        <option value="dining">dining</option>
                        <option value="offers">offers</option>
                        <option value="gallery">gallery</option>
                        <option value="brand">brand</option>
                        <option value="videos">videos</option>
                    </select>
                </div>

                <div class="text-[11px] text-stone-500 font-semibold" id="picker-[#dfe8a6]-info">
                    Click any item to select
                </div>
            </div>

            <!-- Content Grid Area -->
            <div class="flex-1 p-6 overflow-y-auto bg-stone-50/50">
                <div id="picker-grid-container" class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 gap-4">
                    <!-- Dynamic Media Items -->
                </div>
            </div>

            <!-- Footer Pagination -->
            <div class="px-6 py-3 border-t border-stone-200 flex items-center justify-between bg-white text-xs text-stone-500">
                <span id="picker-pagination-info">Showing 0 files</span>
                <div class="flex items-center gap-2">
                    <button type="button" id="picker-btn-prev" onclick="changePickerPage(-1)" disabled class="px-3 py-1 rounded border border-stone-300 bg-white font-semibold disabled:opacity-50">Prev</button>
                    <span id="picker-page-num" class="font-bold text-stone-800">1</span>
                    <button type="button" id="picker-btn-next" onclick="changePickerPage(1)" disabled class="px-3 py-1 rounded border border-stone-300 bg-white font-semibold disabled:opacity-50">Next</button>
                </div>
            </div>
        </div>
    </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function onPickerSearchInput() {
    clearTimeout(pickerSearchTimer);
    pickerSearchTimer = setTimeout(() => {
        activePickerSearch = document.getElementById('picker-search-input').value;
        activePickerPage = 1;
        fetchPickerMediaList();
    }, 300);
}

function onPickerFolderChange() {
    activePickerFolder = document.getElementById('picker-folder-select').value;
    activePickerPage = 1;
    fetchPickerMediaList();
}

function changePickerPage(delta) {
    activePickerPage += delta;
    if (activePickerPage < 1) activePickerPage = 1;
    fetchPickerMediaList();
}

async function fetchPickerMediaList() {
    const container = document.getElementById('picker-grid-container');
    if (!container) return;

    container.innerHTML = `
        <div class="col-span-full py-12 text-center text-stone-400">
            <span class="material-symbols-outlined text-3xl animate-spin">autorenew</span>
            <p class="text-xs font-semibold mt-1">Loading media...</p>
        </div>
    `;

    try {
        const url = window.getApiEndpoint(`/api/media-api.php?action=list&folder=${encodeURIComponent(activePickerFolder)}&type=${encodeURIComponent(activePickerMediaType)}&q=${encodeURIComponent(activePickerSearch)}&page=${activePickerPage}&limit=20`);
        const res = await fetch(url);
        const data = await res.json();

        if (data.success && data.data) {
            renderPickerGrid(data.data);
            updatePickerPagination(data.meta);
        } else {
            container.innerHTML = `<div class="col-span-full py-8 text-center text-rose-500 text-xs font-bold">Failed to load media assets</div>`;
        }
    } catch (err) {
        container.innerHTML = `<div class="col-span-full py-8 text-center text-rose-500 text-xs font-bold">Server connection error</div>`;
    }
}

function renderPickerGrid(items) {
    const container = document.getElementById('picker-grid-container');
    if (!items || items.length === 0) {
        container.innerHTML = `
            <div class="col-span-full py-12 text-center text-stone-400">
                <span class="material-symbols-outlined text-4xl">search_off</span>
                <p class="text-xs font-bold text-stone-600 mt-2">No matching media files</p>
            </div>
        `;
        return;
    }

    container.innerHTML = items.map(item => {
        const isVideo = item.file_type === 'video';
        const itemUrl = window.ensureHttpsUrl(item.url);
        const thumb = isVideo 
            ? `<div class="w-full h-28 bg-stone-950 flex items-center justify-center relative">
                 <video src="${itemUrl}" class="w-full h-full object-cover opacity-75" muted preload="metadata"></video>
                 <span class="material-symbols-outlined text-2xl text-white absolute">play_circle</span>
               </div>`
            : `<div class="w-full h-28 bg-stone-100 overflow-hidden">
                 <img src="${itemUrl}" alt="${item.filename}" class="w-full h-full object-cover" loading="lazy">
               </div>`;

        const escUrl = itemUrl.replace(/'/g, "\\'");

        return `
            <div onclick="selectPickerMediaItem('${escUrl}')" class="bg-white rounded-xl border border-stone-200 shadow-2xs overflow-hidden cursor-pointer hover:border-[#343c0a] hover:ring-2 hover:ring-[#343c0a]/20 transition group">
                ${thumb}
                <div class="p-2 space-y-0.5">
                    <p class="text-[11px] font-bold text-stone-800 truncate" title="${item.filename}">${item.filename}</p>
                    <div class="flex items-center justify-between text-[9px] text-stone-400">
                        <span>${item.folder}</span>
                        <span>${item.formatted_size}</span>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function updatePickerPagination(meta) {
    document.getElementById('picker-pagination-info').textContent = `Showing page ${meta.page} of ${meta.total_pages || 1} (${meta.total} files)`;
    document.getElementById('picker-page-num').textContent = meta.page;
    document.getElementById('picker-btn-prev').disabled = meta.page <= 1;
    document.getElementById('picker-btn-next').disabled = meta.page >= meta.total_pages;
}

window.selectPickerMediaItem = function(mediaUrl) {
    if (!activePickerWidgetId) return;

    mediaUrl = window.ensureHttpsUrl(mediaUrl);

    // Support gallery appending mode for location spots
    if (activePickerWidgetId === 'spot_gallery_append' && typeof window.appendSpotGalleryCard === 'function') {
        window.appendSpotGalleryCard(mediaUrl);
        closeMediaPickerModal();
        return;
    }

    // Support gallery appending mode for rooms
    if (activePickerWidgetId === 'room_gallery_append' && typeof window.appendRoomGalleryCard === 'function') {
        window.appendRoomGalleryCard(mediaUrl);
        closeMediaPickerModal();
        return;
    }

    const { textInput, previewImg, placeholder } = resolveWidgetElements(activePickerWidgetId);

    if (textInput) {
        textInput.value = mediaUrl;
        textInput.dispatchEvent(new Event('input', { bubbles: true }));
        textInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (previewImg) {
        if (previewImg.tagName.toLowerCase() === 'img') {
            previewImg.src = mediaUrl;
            previewImg.classList.remove('hidden');
        } else if (previewImg.tagName.toLowerCase() === 'video') {
            previewImg.src = mediaUrl;
            previewImg.classList.remove('hidden');
            try { previewImg.load(); } catch(e){}
        }
    }

    if (placeholder) {
        placeholder.classList.add('hidden');
    }

    closeMediaPickerModal();
};



