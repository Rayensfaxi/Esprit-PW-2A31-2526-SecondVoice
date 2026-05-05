<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../frontoffice/login.php?status=auth_required');
    exit;
}

if (!in_array(strtolower((string) ($_SESSION['user_role'] ?? 'client')), ['admin', 'agent'], true)) {
    header('Location: ../frontoffice/profile.php?status=forbidden');
    exit;
}

$roleSession = strtolower((string) ($_SESSION['user_role'] ?? 'client'));
if ($roleSession === 'agent') {
    header('Location: gestion-accompagnements.php');
    exit;
}

require_once __DIR__ . '/../../controller/BrainstormingController.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$controller = new BrainstormingController();
$data = $controller->getBrainstormingStatistics();
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SecondVoice | Statistiques des brainstormings</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/style.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
  </head>
  <body data-page="analytics">
    <div class="overlay" data-overlay></div>
    <div class="shell">
      <aside class="sidebar">
        <div class="sidebar-panel">
          <div class="brand-row">
            <a class="brand" href="index.php"><img class="brand-logo" src="assets/media/secondvoice-logo.png" alt="SecondVoice logo" /></a>
          </div>

          <div class="sidebar-scroll">
            <div class="nav-section">
              <div class="nav-title">Gestion</div>
              <a class="nav-link" href="index.php" data-nav="home"><span class="nav-icon icon-home"></span><span>Tableau de bord</span></a>
              <a class="nav-link" href="gestion-utilisateurs.php" data-nav="profile"><span class="nav-icon icon-profile"></span><span>Gestion des utilisateurs</span></a>
              <a class="nav-link" href="gestion-brainstormings.php" data-nav="community"><span class="nav-icon icon-community"></span><span>Gestion des brainstormings</span></a>
              <a class="nav-link" href="gestion-brainstorming-stats.php" data-nav="stats"><span class="nav-icon icon-activity"></span><span>Statistiques</span></a>
              <a class="nav-link" href="gestion-idees.php" data-nav="ideas"><span class="nav-icon icon-community"></span><span>Gestion des idees</span></a>
              <a class="nav-link" href="gestion-rendezvous.php" data-nav="subscription"><span class="nav-icon icon-card"></span><span>Gestion des rendez-vous</span></a>
              <a class="nav-link" href="gestion-accompagnements.php" data-nav="chatbot"><span class="nav-icon icon-chat"></span><span>Gestion des accompagnements</span></a>
              <a class="nav-link" href="gestion-evenements.php" data-nav="images"><span class="nav-icon icon-image"></span><span>Gestion des evenements</span></a>
              <a class="nav-link" href="gestion-reclamations.php" data-nav="voice"><span class="nav-icon icon-mic"></span><span>Gestion des reclamations</span></a>
              <a class="nav-link" href="settings.php" data-nav="settings"><span class="nav-icon icon-settings"></span><span>Parametres</span></a>
            </div>
          </div>
        </div>
      </aside>

      <main class="page">
        <div class="topbar">
          <div>
            <button class="mobile-toggle" data-nav-toggle aria-label="Open navigation">=</button>
            <h1 class="page-title">Statistiques des brainstormings</h1>
            <div class="page-subtitle">Visualisez les metriques et la performance de vos brainstormings.</div>
          </div>
          <div class="toolbar-actions">
            <a class="update-button" href="../frontoffice/index.php">Revenir</a>
            <button class="icon-button icon-moon" data-theme-toggle aria-label="Switch theme"></button>
          </div>
        </div>

        <div class="page-grid">
          <!-- 4 Stats Cards -->
          <section class="stats-grid">
            <div class="stat-card" style="background: linear-gradient(135deg, rgba(99, 91, 255, 0.2) 0%, rgba(99, 91, 255, 0.1) 100%); border: 1px solid rgba(99, 91, 255, 0.3);">
              <div class="small-label" style="color: #9c92ff;">Total Brainstormings</div>
              <div class="metric-number" style="color: #a899ff;"><?= (int) $data['total_brainstormings'] ?></div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, rgba(255, 107, 207, 0.2) 0%, rgba(255, 107, 207, 0.1) 100%); border: 1px solid rgba(255, 107, 207, 0.3);">
              <div class="small-label" style="color: #ff9ed8;">Total Idees</div>
              <div class="metric-number" style="color: #ffb8e6;"><?= (int) $data['total_ideas'] ?></div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, rgba(49, 208, 170, 0.2) 0%, rgba(49, 208, 170, 0.1) 100%); border: 1px solid rgba(49, 208, 170, 0.3);">
              <div class="small-label" style="color: #31d0aa;">Approuves</div>
              <div class="metric-number" style="color: #67ebc0;"><?= (int) $data['approved_brainstormings'] ?></div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, rgba(255, 184, 77, 0.2) 0%, rgba(255, 184, 77, 0.1) 100%); border: 1px solid rgba(255, 184, 77, 0.3);">
              <div class="small-label" style="color: #ffc874;">En attente</div>
              <div class="metric-number" style="color: #ffd89f;"><?= (int) $data['pending_brainstormings'] ?></div>
            </div>
          </section>

          <!-- Charts Section -->
          <section class="charts-section" style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 22px;">
            <!-- Line Chart -->
            <div class="card">
              <div class="card-header">
                <h2 class="panel-title">Brainstormings et Idees par Mois</h2>
              </div>
              <div class="chart-container" style="position: relative; height: 320px; margin: 0;">
                <canvas id="lineChart"></canvas>
              </div>
            </div>

            <!-- Bar Chart -->
            <div class="card">
              <div class="card-header">
                <h2 class="panel-title">Brainstormings par Categorie</h2>
              </div>
              <div class="chart-container" style="position: relative; height: 320px; margin: 0;">
                <canvas id="barChart"></canvas>
              </div>
            </div>
          </section>

          <!-- Top 3 Section -->
          <section class="card">
            <div class="card-header">
              <h2 class="panel-title">Top 3 Brainstormings</h2>
              <span class="helper">Les brainstormings avec le plus d'idees soumises</span>
            </div>
            <div class="top-list">
              <?php foreach ($data['top_brainstormings'] as $index => $brainstorming): ?>
                <div class="top-item" style="display: flex; align-items: center; gap: 16px; padding: 18px 0; border-bottom: 1px solid var(--line); last-child-style: border-bottom: none;">
                  <div class="medal" style="width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.4rem; <?php 
                    if ($index === 0) echo 'background: linear-gradient(135deg, #ffd700, #ffa500); color: #000;';
                    elseif ($index === 1) echo 'background: linear-gradient(135deg, #c0c0c0, #808080); color: #fff;';
                    else echo 'background: linear-gradient(135deg, #cd7f32, #8b4513); color: #fff;';
                  ?>">
                    <?php echo $index + 1; ?>
                  </div>
                  <div style="flex: 1;">
                    <strong style="display: block; font-size: 1.05rem;"><?= h($brainstorming['titre']) ?></strong>
                    <span class="helper"><?= (int) $brainstorming['idea_count'] ?> idee<?= (int) $brainstorming['idea_count'] !== 1 ? 's' : '' ?> soumise<?= (int) $brainstorming['idea_count'] !== 1 ? 's' : '' ?></span>
                  </div>
                  <div style="text-align: right;">
                    <div class="metric-number" style="font-size: 1.8rem; margin: 0;"><?= (int) $brainstorming['idea_count'] ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        </div>
      </main>
    </div>

    <script>
      // Prepare data for charts
      const lineChartData = <?= json_encode($data['line_chart_data']) ?>;
      const barChartData = <?= json_encode($data['bar_chart_data']) ?>;

      // Line Chart - Brainstormings and Ideas per Month
      const lineCtx = document.getElementById('lineChart').getContext('2d');
      new Chart(lineCtx, {
        type: 'line',
        data: {
          labels: lineChartData.labels.reverse(),
          datasets: [
            {
              label: 'Brainstormings',
              data: lineChartData.brainstormings.reverse(),
              borderColor: '#635bff',
              backgroundColor: 'rgba(99, 91, 255, 0.1)',
              borderWidth: 3,
              fill: true,
              tension: 0.4,
              pointRadius: 5,
              pointBackgroundColor: '#635bff',
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
            },
            {
              label: 'Idees',
              data: lineChartData.ideas.reverse(),
              borderColor: '#ff6bcf',
              backgroundColor: 'rgba(255, 107, 207, 0.1)',
              borderWidth: 3,
              fill: true,
              tension: 0.4,
              pointRadius: 5,
              pointBackgroundColor: '#ff6bcf',
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: true,
              position: 'top',
              labels: {
                color: '#96a0b8',
                font: { size: 12, weight: '500' },
                padding: 15,
              }
            },
            filler: {
              propagate: true
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                color: 'rgba(255, 255, 255, 0.06)',
                drawBorder: false,
              },
              ticks: {
                color: '#96a0b8',
              }
            },
            x: {
              grid: {
                display: false,
              },
              ticks: {
                color: '#96a0b8',
              }
            }
          }
        }
      });

      // Bar Chart - Brainstormings per Category
      const barCtx = document.getElementById('barChart').getContext('2d');
      new Chart(barCtx, {
        type: 'bar',
        data: {
          labels: barChartData.labels,
          datasets: [{
            label: 'Nombre',
            data: barChartData.data,
            backgroundColor: [
              'rgba(99, 91, 255, 0.6)',
              'rgba(255, 107, 207, 0.6)',
              'rgba(49, 208, 170, 0.6)',
              'rgba(255, 184, 77, 0.6)',
            ],
            borderColor: [
              '#635bff',
              '#ff6bcf',
              '#31d0aa',
              '#ffc84d',
            ],
            borderWidth: 2,
            borderRadius: 8,
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: true,
              labels: {
                color: '#96a0b8',
                font: { size: 12, weight: '500' },
                padding: 15,
              }
            }
          },
          scales: {
            x: {
              beginAtZero: true,
              grid: {
                color: 'rgba(255, 255, 255, 0.06)',
                drawBorder: false,
              },
              ticks: {
                color: '#96a0b8',
              }
            },
            y: {
              grid: {
                display: false,
              },
              ticks: {
                color: '#96a0b8',
              }
            }
          }
        }
      });
    </script>
    <script>
      // Set active state for stats page
      document.addEventListener('DOMContentLoaded', function() {
        const statsLink = document.querySelector('a[data-nav="stats"]');
        if (statsLink) {
          // Remove active class from all nav links
          document.querySelectorAll('a[data-nav]').forEach(link => {
            link.classList.remove('active');
          });
          // Add active class to stats link
          statsLink.classList.add('active');
        }
      });
    </script>
    <script src="assets/app.js"></script>
  </body>
</html>