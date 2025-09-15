<?php
/**
 * Bears AI Chatbot - Collections View
 *
 * @version 2025.09.15.6
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
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing Articles...';
    btn.disabled = true;
    
    fetch('index.php?option=com_bears_aichatbot&task=syncDocuments', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            // Reload to show updated document counts
            window.location.reload();
        } else {
            alert('Sync failed: ' + data.message);
        }
        btn.innerHTML = originalText;
        btn.disabled = false;
    })
    .catch(error => {
        alert('Sync error: ' + error.message);
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
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
