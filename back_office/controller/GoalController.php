<?php
require_once __DIR__ . '/../model/GoalModel.php';

class GoalController {
    public function listGoals() {
        $goalModel = new GoalModel();
        return $goalModel->getGoals();
    }

    public function showGoal($id) {
        $goalModel = new GoalModel();
        return $goalModel->getGoalById($id);
    }

    public function addGoal() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $goal = [
                'title' => $_POST['title'],
                'description' => $_POST['description'],
                'status' => $_POST['status'],
                'priority' => $_POST['priority'],
                'startDate' => $_POST['startDate'],
                'endDate' => $_POST['endDate'],
                'citoyen_id' => $_POST['citoyen_id'],
                'assistant_id' => $_POST['assistant_id']
            ];
            
            $goalModel = new GoalModel();
            if ($goalModel->addGoal($goal)) {
                header('Location: index-goals.php');
                exit();
            }
        }
    }

    public function updateGoal() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $goal = [
                'title' => $_POST['title'],
                'description' => $_POST['description'],
                'status' => $_POST['status'],
                'priority' => $_POST['priority'],
                'startDate' => $_POST['startDate'],
                'endDate' => $_POST['endDate'],
                'citoyen_id' => $_POST['citoyen_id'],
                'assistant_id' => $_POST['assistant_id']
            ];

            $goalModel = new GoalModel();
            if ($goalModel->updateGoal($id, $goal)) {
                header('Location: index-goals.php');
                exit();
            }
        }
    }

    public function deleteGoal($id) {
        $goalModel = new GoalModel();
        if ($goalModel->deleteGoal($id)) {
            header('Location: index-goals.php');
            exit();
        }
    }
}
?>