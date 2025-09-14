<?php
/**
 * Bears AI Chatbot - Collections View
 *
 * @version 2025.09.14.10
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
<div class="modal fade" id="documentModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo Text::_('COM_BEARS_AICHATBOT_COLLECTION_DOCUMENTS'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
    </div>
  </div>
</div>

<div class="modal fade" id="queryModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo Text::_('COM_BEARS_AICHATBOT_TEST_QUERY'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="queryInput" class="form-label"><?php echo Text::_('COM_BEARS_AICHATBOT_QUERY_TEXT'); ?></label>
          <input type="text" class="form-control" id="queryInput" placeholder="<?php echo Text::_('COM_BEARS_AICHATBOT_QUERY_PLACEHOLDER'); ?>">
        </div>
        <div id="queryResults"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('COM_BEARS_AICHATBOT_CLOSE'); ?></button>
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
    // This would trigger collection creation
    alert('Collection creation functionality would be implemented here');
}

function viewDocuments(collectionId) {
    currentCollectionId = collectionId;
    const modal = new bootstrap.Modal(document.getElementById('documentModal'));
    modal.show();
    
    // Load documents via AJAX
    loadDocuments(collectionId);
}

function testQuery(collectionId) {
    currentCollectionId = collectionId;
    const modal = new bootstrap.Modal(document.getElementById('queryModal'));
    modal.show();
    
    document.getElementById('queryInput').value = '';
    document.getElementById('queryResults').innerHTML = '';
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

function loadDocuments(collectionId) {
    // This would load documents via AJAX
    document.getElementById('documentsContent').innerHTML = '<p>Document loading functionality would be implemented here</p>';
}

function executeQuery() {
    const query = document.getElementById('queryInput').value.trim();
    if (!query) {
        alert('<?php echo Text::_('COM_BEARS_AICHATBOT_ENTER_QUERY'); ?>');
        return;
    }
    
    // This would execute the query via AJAX
    document.getElementById('queryResults').innerHTML = '<p>Query execution functionality would be implemented here</p>';
}
</script>
