/**
 * Indra Hotel - Universal Media & Video Uploader Client-Side Handler with Chunked Streaming & Progress
 */

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

        const response = await fetch((window.BASE_URL || '') + '/api/upload.php', {
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

            const response = await fetch((window.BASE_URL || '') + '/api/upload.php', {
                method: 'POST',
                body: formData
            });
            data = await response.json();
        }

        if (progressBar) progressBar.classList.add('hidden');

        if (data && data.success && data.url) {
            if (textInput) {
                textInput.value = data.url;
                textInput.dispatchEvent(new Event('input', { bubbles: true }));
                textInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (previewImg) {
                previewImg.src = data.url;
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

window.handleImageUrlInput = function (url, widgetId) {
    const previewImg = document.getElementById(widgetId + '_preview');
    const placeholder = document.getElementById(widgetId + '_placeholder');

    const trimmed = (url || '').trim();
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
    const textInput = document.getElementById(widgetId + '_input');
    const previewImg = document.getElementById(widgetId + '_preview');
    const placeholder = document.getElementById(widgetId + '_placeholder');

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
    const previewVideo = document.getElementById(widgetId + '_preview');
    const placeholder = document.getElementById(widgetId + '_placeholder');
    const progressBar = document.getElementById(widgetId + '_progress');
    const progressText = progressBar ? progressBar.querySelector('span:last-child') : null;
    const textInput = document.getElementById(widgetId + '_input');

    // Show progress overlay
    if (progressBar) progressBar.classList.remove('hidden');
    if (progressText) progressText.textContent = 'Uploading Video... 0%';

    try {
        const data = await uploadMediaChunked(file, folder || 'videos', (pct, msg) => {
            if (progressText) progressText.textContent = msg;
        });

        if (progressBar) progressBar.classList.add('hidden');

        if (data && data.success && data.url) {
            if (textInput) {
                textInput.value = data.url;
                textInput.dispatchEvent(new Event('input', { bubbles: true }));
                textInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (previewVideo) {
                previewVideo.src = data.url;
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
    const previewVideo = document.getElementById(widgetId + '_preview');
    const placeholder = document.getElementById(widgetId + '_placeholder');

    const trimmed = (url || '').trim();
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
    const textInput = document.getElementById(widgetId + '_input');
    const previewVideo = document.getElementById(widgetId + '_preview');
    const placeholder = document.getElementById(widgetId + '_placeholder');

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
