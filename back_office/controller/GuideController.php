<?php
require_once __DIR__ . '/../model/GuideModel.php';

class GuideController {
    public function listGuides() {
        $guideModel = new GuideModel();
        return $guideModel->getGuides();
    }

    public function getGuidesByGoal($goal_id) {
        $guideModel = new GuideModel();
        return $guideModel->getGuidesByGoalId($goal_id);
    }

    public function showGuide($id) {
        $guideModel = new GuideModel();
        return $guideModel->getGuideById($id);
    }

    public function addGuide() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $guide = [
                'goal_id' => $_POST['goal_id'],
                'title' => $_POST['title'],
                'content' => $_POST['content'],
                'type' => $_POST['type']
            ];
            
            $guideModel = new GuideModel();
            if ($guideModel->addGuide($guide)) {
                header('Location: index-guides.php');
                exit();
            }
        }
    }

    public function updateGuide() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $guide = [
                'goal_id' => $_POST['goal_id'],
                'title' => $_POST['title'],
                'content' => $_POST['content'],
                'type' => $_POST['type']
            ];

            $guideModel = new GuideModel();
            if ($guideModel->updateGuide($id, $guide)) {
                header('Location: index-guides.php');
                exit();
            }
        }
    }

    public function deleteGuide($id) {
        $guideModel = new GuideModel();
        if ($guideModel->deleteGuide($id)) {
            header('Location: index-guides.php');
            exit();
        }
    }
}
?>