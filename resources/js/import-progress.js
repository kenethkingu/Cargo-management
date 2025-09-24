document.addEventListener('DOMContentLoaded', function () {
    console.log('Import progress script loaded');
    
    const fileInput = document.getElementById('file-upload');
    const fileNameField = document.getElementById('file-name');
    const uploadForm = document.getElementById('uploadForm');
    const progressContainer = document.getElementById('progress-container');
    const progressBarGlobal = document.getElementById('progress-bar-global');
    
    console.log('File input:', fileInput);
    console.log('Upload form:', uploadForm);
    console.log('Progress container:', progressContainer);
    console.log('Batch IDs:', window.importBatchIds);

    // --- File input UI
    if (fileInput && fileNameField) {
        fileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const validExtensions = ['xlsx', 'xls', 'csv'];
                const extension = file.name.split('.').pop().toLowerCase();
                if (!validExtensions.includes(extension)) {
                    if (typeof Toastify !== 'undefined') {
                        Toastify({
                            text: `Invalid file type: ${extension}. Please select an Excel file (.xlsx, .xls, .csv).`,
                            duration: 4000,
                            backgroundColor: '#EF4444' 
                        }).showToast();
                    } else {
                        alert(`Invalid file type: ${extension}. Please select an Excel file (.xlsx, .xls, .csv).`);
                    }
                    e.target.value = '';
                    fileNameField.value = '';
                } else {
                    fileNameField.value = file.name;
                }
            } else {
                fileNameField.value = '';
            }
        });
    }

    // --- Form submission UX
    if (uploadForm) {
        const submitBtn = uploadForm.querySelector('button[type="submit"], input[type="submit"]');
        uploadForm.addEventListener('submit', function (e) {
            console.log('Form submitted');
            
            if (!fileInput || !fileInput.files || !fileInput.files.length) {
                e.preventDefault();
                alert('No file selected! Please select an Excel file before uploading.');
                return;
            }
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.__origText = submitBtn.innerText || submitBtn.value || '';
                if (submitBtn.tagName.toLowerCase() === 'button') submitBtn.innerText = 'Uploading...';
                else submitBtn.value = 'Uploading...';
            }
            
            if (progressContainer) {
                progressContainer.classList.remove('hidden');
                console.log('Progress container shown');
            }
            
            if (progressBarGlobal) {
                progressBarGlobal.style.width = '0%';
                progressBarGlobal.innerText = '0%';
            }
        });
    }

    // --- FIXED Update UI helper
    function updateUI(batchId, data) {
        console.log('Updating UI for batch:', batchId, 'Data:', data);
        
        const processedEl = document.getElementById('processed-' + batchId);
        const statusEl = document.getElementById('status-' + batchId);
        const progressTextEl = document.getElementById('progress-text-' + batchId);
        const progressBarEl = document.getElementById('progress-bar-' + batchId);
        
        console.log('Found elements:', {
            processedEl, statusEl, progressTextEl, progressBarEl
        });
        
        const processedRows = data.processed_rows ?? 0;
        const totalRows = data.total_rows ?? 0;
        const status = data.status ?? 'pending';
        const progress = data.progress ?? 0;
        const pct = Math.min(100, progress);
        const pctText = pct.toFixed(2) + '%';
        
        console.log(`Progress: ${processedRows}/${totalRows} = ${pctText}, Status: ${status}`);
        
        if (processedEl) processedEl.innerText = processedRows;
        if (statusEl) {
            statusEl.innerText = status;
            // CRITICAL FIX: Update status badge classes immediately
            statusEl.className = statusEl.className.replace(
                /bg-(green|red|blue|yellow)-100 text-(green|red|blue|yellow)-800 border border-(green|red|blue|yellow)-200/g, 
                ''
            ).trim();
            
            if (status === 'completed') {
                statusEl.className += ' bg-green-100 text-green-800 border border-green-200';
            } else if (status === 'failed') {
                statusEl.className += ' bg-red-100 text-red-800 border border-red-200';
            } else if (status === 'processing') {
                statusEl.className += ' bg-blue-100 text-blue-800 border border-blue-200';
            } else {
                statusEl.className += ' bg-yellow-100 text-yellow-800 border border-yellow-200';
            }
        }
        
        if (progressTextEl) progressTextEl.innerText = pctText;
        
        if (progressBarEl) {
            progressBarEl.style.width = pct + '%';
            
            // CRITICAL FIX: Remove all color classes first
            progressBarEl.className = progressBarEl.className
                .replace(/bg-(blue|green|red|yellow)-\d+/g, '')
                .trim();
            
            // Add the correct color class based on status
            if (status === 'completed') {
                progressBarEl.classList.add('bg-green-500');
            } else if (status === 'failed') {
                progressBarEl.classList.add('bg-red-500');
            } else if (status === 'processing') {
                progressBarEl.classList.add('bg-blue-500');
            } else {
                progressBarEl.classList.add('bg-yellow-500');
            }
        }

        // Update global progress
        if (progressBarGlobal) {
            progressBarGlobal.style.width = pct + '%';
            progressBarGlobal.innerText = pctText;
            
            // CRITICAL FIX: Update global progress bar color too
            progressBarGlobal.className = progressBarGlobal.className
                .replace(/bg-(blue|green|red|yellow)-\d+/g, '')
                .trim();
                
            if (status === 'completed') {
                progressBarGlobal.classList.add('bg-green-500');
            } else if (status === 'failed') {
                progressBarGlobal.classList.add('bg-red-500');
            } else if (status === 'processing') {
                progressBarGlobal.classList.add('bg-blue-500');
            } else {
                progressBarGlobal.classList.add('bg-yellow-500');
            }
        }
        
        //  Show toast notifications for failed status
        if (status === 'failed') {
            try {
                if (typeof Toastify !== 'undefined') {
                    Toastify({ 
                        text: `Import failed for batch #${batchId}.`, 
                        duration: 6000,
                        backgroundColor: '#EF4444'
                    }).showToast();
                } else {
                    console.error(`Import failed for batch #${batchId}`);
                }
            } catch (err) {
                console.error('Failed to show error notification:', err);
            }
        }
    }

    // --- ENHANCED Polling fallback
    const batchIds = window.importBatchIds || [];
    console.log('Starting polling for batches:', batchIds);
    
    let pollAttempts = 0;
    const maxPollAttempts = 300; // 5 minutes at 2-second intervals
    
    async function poll() {
        if (!batchIds.length) {
            console.log('No batch IDs to poll');
            return;
        }
        
        pollAttempts++;
        
        // Stop polling after max attempts
        if (pollAttempts > maxPollAttempts) {
            console.log('Max poll attempts reached, stopping polling');
            return;
        }
        
        for (const id of batchIds) {
            if (!id) continue;
            
            try {
                console.log(`Polling batch: ${id} (attempt ${pollAttempts})`);
                
                const resp = await fetch(`/cargos/progress/${id}`, { 
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
            
                console.log('Poll response status:', resp.status);
            
                if (!resp.ok) {
                    console.warn('Poll response not OK for batch', id, resp.status);
                    continue;
                }
                
                const data = await resp.json();
                console.log('Poll data received:', data);
            
                if (data.error) {
                    console.error('Error in response:', data.error);
                    continue;
                }
                
                updateUI(id, data);
                
                //  Stop polling for completed/failed batches
                if (data.status === 'completed' || data.status === 'failed') {
                    console.log(`Batch ${id} is ${data.status}, removing from polling`);
                    const index = batchIds.indexOf(id);
                    if (index > -1) {
                        batchIds.splice(index, 1);
                    }
                }
            
            } catch (err) {
                console.error('Polling error for batch', id, err);
            }
        }
        
        // Stop polling if no batches left
        if (batchIds.length === 0) {
            console.log('No more batches to poll, stopping');
            return;
        }
    }
    
    // Start polling immediately and then every 2 seconds
    if (batchIds.length > 0) {
        console.log('Starting polling interval');
        poll(); 
        const pollInterval = setInterval(() => {
            if (batchIds.length === 0) {
                clearInterval(pollInterval);
                console.log('Cleared polling interval - no more batches');
            } else {
                poll();
            }
        }, 2000);
    } else {
        console.log('No batch IDs found, skipping polling');
    }

    // --- Laravel Echo real-time updates
    if (window.Echo && batchIds.length) {
        console.log('Echo found, setting up listeners for batches:', batchIds);
    
        batchIds.forEach(batchId => {
            console.log('Setting up Echo listener for batch:', batchId);
            
            window.Echo.private(`import-progress.${batchId}`)
                .listen('ImportProgressUpdated', (e) => {
                    console.log('Echo event received:', e);
                    
                    if (!e || !e.batch) {
                        console.log('Invalid event data');
                        return;
                    }
                    
                    updateUI(e.batch.id, {
                        processed_rows: e.batch.processed_rows,
                        total_rows: e.batch.total_rows,
                        status: e.batch.status,
                        progress: e.batch.progress || 0 
                    });

                    //  Handle both completed AND failed statuses
                    if (e.batch.status === 'completed') {
                        console.log('Import completed:', e.batch.file_name);
                        try {
                            if (typeof Toastify !== 'undefined') {
                                Toastify({ 
                                    text: `Import "${e.batch.file_name}" completed successfully!`, 
                                    duration: 4000,
                                    backgroundColor: '#10B981'
                                }).showToast();
                            }
                        } catch (err) {
                            console.log('Import completed:', e.batch.file_name);
                        }
                    } else if (e.batch.status === 'failed') {
                        console.log('Import failed:', e.batch.file_name);
                        try {
                            if (typeof Toastify !== 'undefined') {
                                Toastify({ 
                                    text: `Import "${e.batch.file_name}" failed! `, 
                                    duration: 6000,
                                    backgroundColor: '#EF4444'
                                }).showToast();
                            }
                        } catch (err) {
                            console.log('Import failed:', e.batch.file_name);
                        }
                    }
                })
                .error((error) => {
                    console.error('Echo error for batch', batchId, error);
                });
        });
    } else {
        console.log('Echo not available, using polling only');
    }
});