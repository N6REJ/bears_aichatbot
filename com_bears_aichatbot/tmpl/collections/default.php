<?php
/**
 * Bears AI Chatbot - Collections View
 *
 * @version 2025.10.02.2
 * @package Bears AI Chatbot
 * @author N6REJ
 * @email troy@hallhome.us
 * @website https://www.hallhome.us
 * @copyright Copyright (C) 2025 Troy Hall (N6REJ)
 * @license GNU General Public License version 3 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

// Variables passed from the main component file
/** @var string $title */
/** @var array $collections */
/** @var string $error */
/** @var array $moduleConfig */

// Provide defaults in case variables aren't set
$title = $title ?? Text::_('COM_BEARS_AICHATBOT_COLLECTIONS_TITLE');
$collections = $collections ?? [];
$error = $error ?? '';
$moduleConfig = $moduleConfig ?? [];

// Helper function to format file sizes
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Helper function to format dates
function formatDate($dateString) {
    if (!$dateString) return 'N/A';
    try {
        $date = new DateTime($dateString);
        return $date->format('M j, Y g:i A');
    } catch (Exception $e) {
        return $dateString;
    }
}
?>
<div class="com-bears-aichatbot">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="page-title">
        <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
      </h1>
      <div class="page-actions">
        <a href="<?php echo \Joomla\CMS\Router\Route::_('index.php?option=com_bears_aichatbot&view=dashboard'); ?>" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> <?php echo Text::_('COM_BEARS_AICHATBOT_BACK_TO_DASHBOARD'); ?>
        </a>
        <?php if (!empty($collections)): ?>
        <button type="button" class="btn btn-danger" onclick="deleteAllCollections()">
          <i class="fas fa-trash-alt"></i> <?php echo Text::_('COM_BEARS_AICHATBOT_DELETE_ALL'); ?>
        </button>
        <?php endif; ?>
        <button type="button" class="btn btn-success" onclick="syncDocuments()">
          <i class="fas fa-file-upload"></i> Sync Articles to Collection
        </button>
        <button type="button" class="btn btn-primary" onclick="refreshCollections()">
          <i class="fas fa-sync-alt"></i> <?php echo Text::_('COM_BEARS_AICHATBOT_REFRESH'); ?>
        </button>
      </div>
    </div>

    <?php if ($error): ?>
    <!-- Error Message -->
    <div class="alert alert-danger" role="alert">
      <h4 class="alert-heading"><?php echo Text::_('COM_BEARS_AICHATBOT_ERROR'); ?></h4>
      <p class="mb-0"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <?php endif; ?>

    <?php if (empty($collections) && !$error): ?>
    <!-- No Collections -->
    <div class="alert alert-info text-center" role="alert">
      <h4 class="alert-heading"><?php echo Text::_('COM_BEARS_AICHATBOT_NO_COLLECTIONS'); ?></h4>
      <p><?php echo Text::_('COM_BEARS_AICHATBOT_NO_COLLECTIONS_DESC'); ?></p>
      <hr>
      <p class="mb-0">
        <button type="button" class="btn btn-primary" onclick="createCollection()">
          <i class="fas fa-plus"></i> <?php echo Text::_('COM_BEARS_AICHATBOT_CREATE_COLLECTION'); ?>
        </button>
      </p>
    </div>
    <?php endif; ?>

    <?php if (!empty($collections)): ?>
    <!-- Collections Grid -->
    <div class="row g-4">
      <?php foreach ($collections as $collection): ?>
      <div class="col-12 col-lg-6 col-xl-4">
        <div class="card collection-card h-100">
          <div class="card-header d-flex justify-content-between align-items-start">
            <div>
              <h5 class="card-title mb-1"><?php echo htmlspecialchars($collection['name'] ?? 'Unnamed Collection', ENT_QUOTES, 'UTF-8'); ?></h5>
              <small class="text-muted">ID: <?php echo htmlspecialchars(substr($collection['id'] ?? '', 0, 16) . '...', ENT_QUOTES, 'UTF-8'); ?></small>
            </div>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-ellipsis-v"></i>
              </button>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="viewDocuments('<?php echo htmlspecialchars($collection['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>')">
                  <i class="fas fa-file-alt"></i> <?php echo Text::_('COM_BEARS_AICHATBOT_VIEW_DOCUMENTS'); ?>
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="testQuery('<?php echo htmlspecialchars($collection['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>')">
                  <i class="fas fa-search"></i> <?php echo Text::_('COM_BEARS_AICHATBOT_TEST_QUERY'); ?>
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="#" onclick="deleteCollection('<?php echo htmlspecialchars($collection['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>')">
                  <i class="fas fa-trash"></i> <?php echo Text::_('COM_BEARS_AICHATBOT_DELETE_COLLECTION'); ?>
                </a></li>
              </ul>
            </div>
          </div>
          
          <div class="card-body">
            <?php if (!empty($collection['description'])): ?>
            <p class="card-text text-muted mb-3"><?php echo htmlspecialchars($collection['description'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            
            <!-- Collection Stats -->
            <div class="collection-stats mb-3">
              <div class="row g-2">
                <div class="col-6">
                  <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($collection['document_count'] ?? 0); ?></div>
                    <div class="stat-label"><?php echo Text::_('COM_BEARS_AICHATBOT_DOCUMENTS'); ?></div>
                  </div>
                </div>
                <div class="col-6">
                  <div class="stat-item">
                    <div class="stat-value"><?php echo formatBytes($collection['size_bytes'] ?? 0); ?></div>
                    <div class="stat-label"><?php echo Text::_('COM_BEARS_AICHATBOT_SIZE'); ?></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Collection Metadata -->
            <div class="collection-metadata">
              <small class="text-muted d-block mb-1">
                <i class="fas fa-calendar-plus"></i> 
                <?php echo Text::_('COM_BEARS_AICHATBOT_CREATED'); ?>: 
                <?php echo formatDate($collection['created_at'] ?? null); ?>
              </small>
              
              <?php if (!empty($collection['updated_at'])): ?>
              <small class="text-muted d-block mb-1">
                <i class="fas fa-calendar-edit"></i> 
                <?php echo Text::_('COM_BEARS_AICHATBOT_UPDATED'); ?>: 
                <?php echo formatDate($collection['updated_at']); ?>
              </small>
              <?php endif; ?>
              
              <?php if (!empty($collection['embedding_model'])): ?>
              <small class="text-muted d-block mb-1">
                <i class="fas fa-brain"></i> 
                <?php echo Text::_('COM_BEARS_AICHATBOT_EMBEDDING_MODEL'); ?>: 
                <?php echo htmlspecialchars($collection['embedding_model'], ENT_QUOTES, 'UTF-8'); ?>
              </small>
              <?php endif; ?>
              
              <?php if (isset($collection['status'])): ?>
              <small class="d-block">
                <span class="badge bg-<?php echo $collection['status'] === 'ready' ? 'success' : ($collection['status'] === 'processing' ? 'warning' : 'secondary'); ?>">
                  <?php echo ucfirst($collection['status'] ?? 'unknown'); ?>
                </span>
              </small>
              <?php endif; ?>
            </div>
          </div>
          
          <?php if (isset($moduleConfig['collection_id']) && $moduleConfig['collection_id'] === $collection['id']): ?>
          <div class="card-footer bg-primary text-white">
            <small><i class="fas fa-star"></i> <?php echo Text::_('COM_BEARS_AICHATBOT_ACTIVE_COLLECTION'); ?></small>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modals and JavaScript -->
<div class="modal fade" id="documentModal" tabindex="-1" aria-labelledby="documentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="documentModalLabel"><?php echo Text::_('COM_BEARS_AICHATBOT_COLLECTION_DOCUMENTS'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="closeModal('documentModal')"></button>
      </div>
      <div class="modal-body">
        <div id="documentsContent">
          <div class="text-center">
            <div class="spinner-border" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeModal('documentModal')"><?php echo Text::_('COM_BEARS_AICHATBOT_CLOSE'); ?></button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="queryModal" tabindex="-1" aria-labelledby="queryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="queryModalLabel"><?php echo Text::_('COM_BEARS_AICHATBOT_TEST_QUERY'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="closeModal('queryModal')"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="queryInput" class="form-label"><?php echo Text::_('COM_BEARS_AICHATBOT_QUERY_TEXT'); ?></label>
          <input type="text" class="form-control" id="queryInput" placeholder="<?php echo Text::_('COM_BEARS_AICHATBOT_QUERY_PLACEHOLDER'); ?>">
        </div>
        <div id="queryResults"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeModal('queryModal')"><?php echo Text::_('COM_BEARS_AICHATBOT_CLOSE'); ?></button>
        <button type="button" class="btn btn-primary" onclick="executeQuery()"><?php echo Text::_('COM_BEARS_AICHATBOT_EXECUTE_QUERY'); ?></button>
      </div>
    </div>
  </div>
</div>

<script>
let currentCollectionId = '';

function refreshCollections() {
    window.location.reload();
}

function createCollection() {
    // Show a modal or form for collection creation
    const name = prompt('<?php echo Text::_('COM_BEARS_AICHATBOT_ENTER_COLLECTION_NAME'); ?>', 'bears-aichatbot-' + Date.now());
    
    if (!name) {
        return;
    }
    
    const description = prompt('<?php echo Text::_('COM_BEARS_AICHATBOT_ENTER_COLLECTION_DESC'); ?>', 'AI Chatbot Document Collection');
    
    if (name) {
        // Show loading state
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
        btn.disabled = true;
        
        // Make AJAX request to create collection
        fetch('index.php?option=com_bears_aichatbot&task=createCollection', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'name=' + encodeURIComponent(name) + '&description=' + encodeURIComponent(description || '')
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('<?php echo Text::_('COM_BEARS_AICHATBOT_COLLECTION_CREATED'); ?>');
                window.location.reload();
            } else {
                alert('<?php echo Text::_('COM_BEARS_AICHATBOT_CREATE_ERROR'); ?>: ' + (data.message || 'Unknown error'));
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(error => {
            alert('<?php echo Text::_('COM_BEARS_AICHATBOT_CREATE_ERROR'); ?>: ' + error.message);
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
}

function viewDocuments(collectionId) {
    currentCollectionId = collectionId;
    
    // Use Joomla's Bootstrap modal (already initialized)
    const modalElement = document.getElementById('documentModal');
    
    // For Joomla 4/5, use the data-bs attributes
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
        modal.show();
    } else {
        // Fallback for older Joomla or if Bootstrap isn't loaded
        // Use jQuery if available
        if (typeof jQuery !== 'undefined') {
            jQuery('#documentModal').modal('show');
        } else {
            // Manual fallback
            modalElement.classList.add('show');
            modalElement.style.display = 'block';
            modalElement.setAttribute('aria-modal', 'true');
            modalElement.removeAttribute('aria-hidden');
            
            // Add backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.id = 'modal-backdrop-temp';
            document.body.appendChild(backdrop);
        }
    }
    
    // Load documents via AJAX
    loadDocuments(collectionId);
}

function testQuery(collectionId) {
    currentCollectionId = collectionId;
    
    // Use Joomla's Bootstrap modal (already initialized)
    const modalElement = document.getElementById('queryModal');
    
    // For Joomla 4/5, use the data-bs attributes
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
        modal.show();
    } else {
        // Fallback for older Joomla or if Bootstrap isn't loaded
        // Use jQuery if available
        if (typeof jQuery !== 'undefined') {
            jQuery('#queryModal').modal('show');
        } else {
            // Manual fallback
            modalElement.classList.add('show');
            modalElement.style.display = 'block';
            modalElement.setAttribute('aria-modal', 'true');
            modalElement.removeAttribute('aria-hidden');
            
            // Add backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.id = 'modal-backdrop-temp';
            document.body.appendChild(backdrop);
        }
    }
    
    document.getElementById('queryInput').value = '';
    document.getElementById('queryResults').innerHTML = '';
}

// Add close modal function for manual fallback
function closeModal(modalId) {
    const modalElement = document.getElementById(modalId);
    
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) modal.hide();
    } else if (typeof jQuery !== 'undefined') {
        jQuery('#' + modalId).modal('hide');
    } else {
        // Manual close
        modalElement.classList.remove('show');
        modalElement.style.display = 'none';
        modalElement.setAttribute('aria-hidden', 'true');
        modalElement.removeAttribute('aria-modal');
        
        // Remove backdrop
        const backdrop = document.getElementById('modal-backdrop-temp');
        if (backdrop) backdrop.remove();
    }
}

function deleteCollection(collectionId) {
    if (confirm('<?php echo Text::_('COM_BEARS_AICHATBOT_CONFIRM_DELETE'); ?>')) {
        // Show loading state
        const deleteButton = document.querySelector(`[onclick="deleteCollection('${collectionId}')"]`);
        if (deleteButton) {
            deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo Text::_('COM_BEARS_AICHATBOT_DELETING'); ?>';
            deleteButton.disabled = true;
        }
        
        // Make AJAX request to delete collection
        fetch('index.php?option=com_bears_aichatbot&task=deleteCollection', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'collection_id=' + encodeURIComponent(collectionId)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                alert('<?php echo Text::_('COM_BEARS_AICHATBOT_DELETE_SUCCESS'); ?>: ' + data.message);
                // Reload the page to refresh the collections list
                window.location.reload();
            } else {
                // Show error message
                alert('<?php echo Text::_('COM_BEARS_AICHATBOT_DELETE_ERROR'); ?>: ' + data.message);
                // Restore button state
                if (deleteButton) {
                    deleteButton.innerHTML = '<i class="fas fa-trash"></i> <?php echo Text::_('COM_BEARS_AICHATBOT_DELETE_COLLECTION'); ?>';
                    deleteButton.disabled = false;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('<?php echo Text::_('COM_BEARS_AICHATBOT_DELETE_ERROR'); ?>: ' + error.message);
            // Restore button state
            if (deleteButton) {
                deleteButton.innerHTML = '<i class="fas fa-trash"></i> <?php echo Text::_('COM_BEARS_AICHATBOT_DELETE_COLLECTION'); ?>';
                deleteButton.disabled = false;
            }
        });
    }
}

function deleteAllCollections() {
    // Get all collection IDs from the page
    const collections = <?php echo json_encode($collections); ?>;
    
    if (collections.length === 0) {
        alert('No collections to delete');
        return;
    }
    
    const confirmMessage = 'Are you sure you want to delete ALL ' + collections.length + ' collections?\n\n' +
                          'This action cannot be undone and will permanently delete:\n' +
                          '• All collections\n' +
                          '• All documents in those collections\n' +
                          '• All associated data\n\n' +
                          'Type "DELETE ALL" to confirm:';
    
    const userConfirmation = prompt(confirmMessage);
    
    if (userConfirmation !== 'DELETE ALL') {
        alert('Deletion cancelled. You must type "DELETE ALL" to confirm.');
        return;
    }
    
    // Show loading state on the button
    const deleteAllButton = document.querySelector('[onclick="deleteAllCollections()"]');
    if (deleteAllButton) {
        deleteAllButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting All Collections...';
        deleteAllButton.disabled = true;
    }
    
    // Track deletion progress
    let deleted = 0;
    let failed = 0;
    const errors = [];
    
    // Create a progress display
    const progressDiv = document.createElement('div');
    progressDiv.className = 'alert alert-info position-fixed top-50 start-50 translate-middle';
    progressDiv.style.zIndex = '9999';
    progressDiv.innerHTML = '<h5>Deleting Collections...</h5><div class="progress"><div class="progress-bar" role="progressbar" style="width: 0%"></div></div><p class="mt-2 mb-0">Processing: 0 / ' + collections.length + '</p>';
    document.body.appendChild(progressDiv);
    
    const progressBar = progressDiv.querySelector('.progress-bar');
    const progressText = progressDiv.querySelector('p');
    
    // Function to delete a single collection
    function deleteNextCollection(index) {
        if (index >= collections.length) {
            // All done
            progressDiv.remove();
            
            let message = 'Deletion complete!\n\n';
            message += 'Successfully deleted: ' + deleted + ' collections\n';
            if (failed > 0) {
                message += 'Failed to delete: ' + failed + ' collections\n\n';
                message += 'Errors:\n' + errors.join('\n');
            }
            
            alert(message);
            
            // Reload the page
            window.location.reload();
            return;
        }
        
        const collection = collections[index];
        const collectionId = collection.id || collection.collection_id;
        const collectionName = collection.name || 'Unknown';
        
        // Update progress
        const progress = Math.round((index / collections.length) * 100);
        progressBar.style.width = progress + '%';
        progressText.textContent = 'Processing: ' + (index + 1) + ' / ' + collections.length + ' - ' + collectionName;
        
        // Delete the collection
        fetch('index.php?option=com_bears_aichatbot&task=deleteCollection', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'collection_id=' + encodeURIComponent(collectionId)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                deleted++;
            } else {
                failed++;
                errors.push(collectionName + ': ' + (data.message || 'Unknown error'));
            }
            // Continue with next collection
            deleteNextCollection(index + 1);
        })
        .catch(error => {
            failed++;
            errors.push(collectionName + ': ' + error.message);
            // Continue with next collection even if this one failed
            deleteNextCollection(index + 1);
        });
    }
    
    // Start the deletion process
    deleteNextCollection(0);
}

function syncDocuments() {
    if (!confirm('This will sync all articles from selected categories to the active collection. Continue?')) {
        return;
    }
    
    // Show loading state
    const btn = event.target || document.querySelector('[onclick="syncDocuments()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing Articles...';
    btn.disabled = true;
    
    // Create a prominent progress overlay
    const progressOverlay = document.createElement('div');
    progressOverlay.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
    progressOverlay.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
    progressOverlay.style.zIndex = '10000';
    progressOverlay.innerHTML = `
        <div class="card" style="min-width: 500px; max-width: 600px;">
            <div class="card-body">
                <h5 class="card-title text-center mb-3">
                    <i class="fas fa-sync-alt fa-spin me-2"></i>Syncing Articles to Collection
                </h5>
                <div class="progress mb-3" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                         role="progressbar" 
                         style="width: 0%"
                         aria-valuenow="0" 
                         aria-valuemin="0" 
                         aria-valuemax="100">0%</div>
                </div>
                <p class="text-center mb-2" id="sync-status">Initializing sync process...</p>
                <small class="text-muted d-block text-center mb-3" id="sync-details">Please wait, this may take a few moments</small>
                
                <!-- Article being processed indicator -->
                <div id="current-article" class="alert alert-info d-none" role="alert">
                    <div class="d-flex align-items-center">
                        <div class="spinner-border spinner-border-sm me-2" role="status">
                            <span class="visually-hidden">Processing...</span>
                        </div>
                        <div>
                            <strong>Processing:</strong> <span id="article-title"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Success/Failed counters -->
                <div class="row text-center d-none" id="sync-counters">
                    <div class="col-6">
                        <span class="badge bg-success fs-6">
                            <i class="fas fa-check-circle"></i> Synced: <span id="synced-count">0</span>
                        </span>
                    </div>
                    <div class="col-6">
                        <span class="badge bg-danger fs-6">
                            <i class="fas fa-times-circle"></i> Failed: <span id="failed-count">0</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(progressOverlay);
    
    const progressBar = progressOverlay.querySelector('.progress-bar');
    const statusText = progressOverlay.querySelector('#sync-status');
    const detailsText = progressOverlay.querySelector('#sync-details');
    const currentArticleDiv = progressOverlay.querySelector('#current-article');
    const articleTitleSpan = progressOverlay.querySelector('#article-title');
    const syncCountersDiv = progressOverlay.querySelector('#sync-counters');
    const syncedCountSpan = progressOverlay.querySelector('#synced-count');
    const failedCountSpan = progressOverlay.querySelector('#failed-count');
    
    let totalArticles = 0;
    let currentIndex = 0;
    let syncedCount = 0;
    let failedCount = 0;
    
    // Start with initial progress to show activity
    setTimeout(() => {
        progressBar.style.width = '5%';
        progressBar.textContent = '5%';
        progressBar.setAttribute('aria-valuenow', 5);
        statusText.textContent = 'Connecting to server...';
        detailsText.textContent = 'Preparing to sync articles';
    }, 100);
    
    // Simulate progress stages while waiting for response
    let progressInterval = setInterval(() => {
        let currentProgress = parseInt(progressBar.getAttribute('aria-valuenow'));
        if (currentProgress < 90) {
            let newProgress = Math.min(currentProgress + Math.random() * 5, 90);
            progressBar.style.width = newProgress + '%';
            progressBar.textContent = Math.floor(newProgress) + '%';
            progressBar.setAttribute('aria-valuenow', newProgress);
            
            // Update status based on progress
            if (newProgress < 20) {
                statusText.textContent = 'Checking collection status...';
                detailsText.textContent = 'Verifying IONOS connection';
            } else if (newProgress < 40) {
                statusText.textContent = 'Loading articles...';
                detailsText.textContent = 'Fetching articles from selected categories';
            } else if (newProgress < 60) {
                statusText.textContent = 'Processing articles...';
                detailsText.textContent = 'Preparing documents for sync';
                syncCountersDiv.classList.remove('d-none');
            } else if (newProgress < 80) {
                statusText.textContent = 'Syncing to collection...';
                detailsText.textContent = 'Uploading documents to IONOS';
                currentArticleDiv.classList.remove('d-none');
                articleTitleSpan.textContent = 'Processing batch...';
            } else {
                statusText.textContent = 'Finalizing sync...';
                detailsText.textContent = 'Almost complete';
            }
        }
    }, 500);
    
    // Use regular AJAX instead of SSE (SSE not working properly in Joomla)
    fetch('index.php?option=com_bears_aichatbot&task=syncDocuments', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        // Stop the progress simulation
        clearInterval(progressInterval);
        
        // Set to 100% complete
        progressBar.style.width = '100%';
        progressBar.textContent = '100%';
        progressBar.setAttribute('aria-valuenow', 100);
        progressBar.classList.remove('progress-bar-animated');
        
        if (data.success) {
            // Show success state
            progressBar.classList.add('bg-success');
            statusText.textContent = 'Sync completed successfully!';
            detailsText.textContent = data.message;
            
            // Update counters if available
            if (data.synced !== undefined) {
                syncedCountSpan.textContent = data.synced;
            }
            if (data.failed !== undefined) {
                failedCountSpan.textContent = data.failed;
            }
            
            // Wait a moment then reload
            setTimeout(() => {
                progressOverlay.remove();
                alert(data.message);
                window.location.reload();
            }, 2000);
        } else {
            // Show error state
            progressBar.classList.add('bg-danger');
            statusText.textContent = 'Sync failed!';
            detailsText.textContent = data.message;
            currentArticleDiv.classList.add('d-none');
            
            // Update counters if available
            if (data.synced !== undefined) {
                syncedCountSpan.textContent = data.synced;
            }
            if (data.failed !== undefined) {
                failedCountSpan.textContent = data.failed;
            }
            
            // Wait a moment then close
            setTimeout(() => {
                progressOverlay.remove();
                alert('Sync failed: ' + data.message);
            }, 3000);
        }
        
        // Re-enable button
        btn.innerHTML = originalText;
        btn.disabled = false;
    })
    .catch(error => {
        // Stop the progress simulation
        clearInterval(progressInterval);
        
        // Show error state
        progressBar.classList.remove('progress-bar-animated');
        progressBar.classList.add('bg-warning');
        statusText.textContent = 'Connection error';
        detailsText.textContent = 'Failed to communicate with server';
        currentArticleDiv.classList.add('d-none');
        
        setTimeout(() => {
            progressOverlay.remove();
            console.error('Sync error:', error);
            alert('An error occurred during sync: ' + error.message);
        }, 3000);
        
        // Re-enable button
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
    
    return;
    
    // OLD SSE CODE - DISABLED - This code is kept for reference but not executed
    /*
    const eventSource = new EventSource('index.php?option=com_bears_aichatbot&task=syncDocuments');
    
    eventSource.addEventListener('start', function(e) {
        const data = JSON.parse(e.data);
        totalArticles = data.total;
        statusText.textContent = data.message;
        detailsText.textContent = 'Processing ' + totalArticles + ' articles...';
    });
    
    eventSource.addEventListener('progress', function(e) {
        const data = JSON.parse(e.data);
        currentIndex = data.current;
        
        // Update progress bar
        const percentage = data.percentage;
        progressBar.style.width = percentage + '%';
        progressBar.textContent = percentage + '%';
        progressBar.setAttribute('aria-valuenow', percentage);
        
        // Show current article being processed
        currentArticleDiv.classList.remove('d-none');
        articleTitleSpan.textContent = data.article_title + ' (ID: ' + data.article_id + ')';
        
        // Update status
        statusText.textContent = 'Processing article ' + currentIndex + ' of ' + totalArticles;
        
        // Show counters
        syncCountersDiv.classList.remove('d-none');
    });
    
    eventSource.addEventListener('article_synced', function(e) {
        const data = JSON.parse(e.data);
        syncedCount = data.synced;
        failedCount = data.failed;
        syncedCountSpan.textContent = syncedCount;
        failedCountSpan.textContent = failedCount;
        
        // Flash success for current article
        currentArticleDiv.classList.remove('alert-info', 'alert-danger');
        currentArticleDiv.classList.add('alert-success');
        setTimeout(() => {
            currentArticleDiv.classList.remove('alert-success');
            currentArticleDiv.classList.add('alert-info');
        }, 500);
    });
    
    eventSource.addEventListener('article_failed', function(e) {
        const data = JSON.parse(e.data);
        syncedCount = data.synced;
        failedCount = data.failed;
        syncedCountSpan.textContent = syncedCount;
        failedCountSpan.textContent = failedCount;
        
        // Flash error for current article
        currentArticleDiv.classList.remove('alert-info', 'alert-success');
        currentArticleDiv.classList.add('alert-danger');
        articleTitleSpan.innerHTML = data.article_title + ' (ID: ' + data.article_id + ') - <small>' + data.error + '</small>';
        setTimeout(() => {
            currentArticleDiv.classList.remove('alert-danger');
            currentArticleDiv.classList.add('alert-info');
        }, 1000);
    });
    
    eventSource.addEventListener('collection_created', function(e) {
        const data = JSON.parse(e.data);
        statusText.textContent = 'New collection created: ' + data.name;
        detailsText.textContent = 'Collection ID: ' + data.collection_id;
    });
    
    eventSource.addEventListener('complete', function(e) {
        const data = JSON.parse(e.data);
        eventSource.close();
        
        // Update final UI
        progressBar.style.width = '100%';
        progressBar.textContent = '100%';
        progressBar.setAttribute('aria-valuenow', 100);
        progressBar.classList.remove('progress-bar-animated');
        progressBar.classList.add('bg-success');
        
        currentArticleDiv.classList.add('d-none');
        statusText.textContent = 'Sync completed successfully!';
        detailsText.innerHTML = data.message;
        
        setTimeout(() => {
            progressOverlay.remove();
            alert(data.message);
            window.location.reload();
        }, 3000);
        
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
    
    eventSource.addEventListener('error', function(e) {
        eventSource.close();
        
        let errorMessage = 'Unknown error occurred';
        try {
            const data = JSON.parse(e.data);
            errorMessage = data.message;
        } catch (parseError) {
            // If we can't parse the error, use default message
        }
        
        progressBar.classList.remove('progress-bar-animated');
        progressBar.classList.add('bg-danger');
        currentArticleDiv.classList.add('d-none');
        statusText.textContent = 'Sync failed!';
        detailsText.textContent = errorMessage;
        
        setTimeout(() => {
            progressOverlay.remove();
            alert('Sync failed: ' + errorMessage);
        }, 3000);
        
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
    
    eventSource.onerror = function(e) {
        // Connection error or stream ended
        if (eventSource.readyState === EventSource.CLOSED) {
            // Stream closed normally (might be complete)
            console.log('SSE connection closed');
        } else {
            // Actual error
            console.error('SSE error:', e);
            eventSource.close();
            
            progressBar.classList.remove('progress-bar-animated');
            progressBar.classList.add('bg-warning');
            statusText.textContent = 'Connection lost';
            detailsText.textContent = 'The sync process may have completed or encountered an error';
            
            setTimeout(() => {
                progressOverlay.remove();
                window.location.reload();
            }, 3000);
        }
        
        btn.innerHTML = originalText;
        btn.disabled = false;
    };
    */
}

function loadDocuments(collectionId) {
    // Load documents via AJAX
    document.getElementById('documentsContent').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    fetch('index.php?option=com_bears_aichatbot&task=getDocuments&collection_id=' + encodeURIComponent(collectionId), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.documents) {
            let html = '';
            if (data.documents.length === 0) {
                html = '<p class="text-center">No documents in this collection yet. Click "Sync Articles to Collection" to add your Joomla articles.</p>';
            } else {
                html = '<div class="list-group">';
                data.documents.forEach(doc => {
                    const metadata = doc.metadata || {};
                    html += `
                        <div class="list-group-item">
                            <h6>${metadata.title || 'Untitled Document'}</h6>
                            <small class="text-muted">
                                Article ID: ${metadata.article_id || 'N/A'} | 
                                Created: ${metadata.created || 'N/A'}
                            </small>
                            <p class="mb-0 mt-2">${(doc.content || '').substring(0, 200)}...</p>
                        </div>
                    `;
                });
                html += '</div>';
            }
            document.getElementById('documentsContent').innerHTML = html;
        } else {
            document.getElementById('documentsContent').innerHTML = '<p class="text-danger">Failed to load documents: ' + (data.message || 'Unknown error') + '</p>';
        }
    })
    .catch(error => {
        document.getElementById('documentsContent').innerHTML = '<p class="text-danger">Error loading documents: ' + error.message + '</p>';
    });
}

function executeQuery() {
    const query = document.getElementById('queryInput').value.trim();
    if (!query) {
        alert('<?php echo Text::_('COM_BEARS_AICHATBOT_ENTER_QUERY'); ?>');
        return;
    }
    
    // Show loading state
    document.getElementById('queryResults').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Searching...</span></div></div>';
    
    fetch('index.php?option=com_bears_aichatbot&task=testQuery', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'collection_id=' + encodeURIComponent(currentCollectionId) + '&query=' + encodeURIComponent(query)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.results) {
            let html = '';
            if (data.results.length === 0) {
                html = '<p>No results found for your query.</p>';
            } else {
                html = '<h6>Search Results:</h6><div class="list-group">';
                data.results.forEach((result, index) => {
                    const score = result.score || result.relevance || 0;
                    const content = result.content || result.text || '';
                    const metadata = result.metadata || {};
                    
                    html += `
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <h6>Result ${index + 1}</h6>
                                <span class="badge bg-info">Score: ${score.toFixed(3)}</span>
                            </div>
                            <small class="text-muted">Article: ${metadata.title || 'Unknown'}</small>
                            <p class="mb-0 mt-2">${content.substring(0, 300)}...</p>
                        </div>
                    `;
                });
                html += '</div>';
            }
            document.getElementById('queryResults').innerHTML = html;
        } else {
            document.getElementById('queryResults').innerHTML = '<p class="text-danger">Search failed: ' + (data.message || 'Unknown error') + '</p>';
        }
    })
    .catch(error => {
        document.getElementById('queryResults').innerHTML = '<p class="text-danger">Search error: ' + error.message + '</p>';
    });
}
</script>
