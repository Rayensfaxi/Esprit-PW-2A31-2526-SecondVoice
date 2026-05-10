<?php
require_once '../../../controller/ServiceC.php';
$serviceC = new ServiceC();

// Recherche
$search = $_GET['search'] ?? '';

// Récupération de la liste
$liste = $serviceC->listServices($search);
?>

<section class="table-card" style="grid-column: 1 / -1;">
  <div class="card-header" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 1rem;">
      <h3 class="panel-title">Liste des services</h3>
      <div class="users-actions" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
        <div style="position: relative; flex: 1; min-width: 250px;">
          <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); opacity: 0.5;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
          </span>
          <input type="text" id="serviceSearch" placeholder="Rechercher un service..." 
                 style="width: 100%; height: 42px; padding: 0 14px 0 40px; border-radius: var(--radius-sm); border: 1px solid var(--input-border); background: var(--input-bg); color: var(--text); outline: none;"
                 value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button class="action-button" style="background: var(--primary); border-color: var(--primary); height: 42px; color: var(--text);" type="button" onclick="openAddModal()">Ajouter un service</button>
      </div>
    </div>
  </div>
  <table class="table users-table">
    <thead>
      <tr>
        <th>Nom</th>
        <th>Description</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="serviceTableBody">
      <?php foreach ($liste as $service): ?>
      <tr>
        <td><strong><?php echo htmlspecialchars($service->getNom()); ?></strong></td>
        <td><?php echo htmlspecialchars($service->getDescription()); ?></td>
        <td>
          <div style="display: flex; gap: 8px;">
            <button type="button" class="view-btn" onclick='editService(<?php echo json_encode([
                "id" => $service->getId(),
                "nom" => $service->getNom(),
                "description" => $service->getDescription()
            ]); ?>)'>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            </button>
            <button type="button" class="view-btn" onclick="confirmDeleteService(<?php echo $service->getId(); ?>)" style="background: #ef444415; border-color: #ef4444; color: #ef4444;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

