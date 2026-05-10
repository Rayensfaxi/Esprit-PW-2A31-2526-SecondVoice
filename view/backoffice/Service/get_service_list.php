<?php
require_once '../../../controller/ServiceC.php';
$serviceC = new ServiceC();

// Recherche
$search = $_GET['search'] ?? '';

// Récupération de la liste
$liste = $serviceC->listServices($search);

if (empty($liste)) {
    echo '<tr><td colspan="3" style="text-align: center; padding: 2rem; color: var(--muted);">Aucun service trouvé.</td></tr>';
} else {
    foreach ($liste as $service) {
        ?>
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
        <?php
    }
}
?>
