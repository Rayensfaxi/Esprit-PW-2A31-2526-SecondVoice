<?php
require_once __DIR__ . '/../config.php';

class Goal
{
    private ?int $id = null;
    private int $user_id;
    private int $selected_assistant_id;
    private string $title;
    private string $description;
    private string $type;
    private string $admin_validation_status;
    private string $assistant_validation_status;
    private string $status;
    private ?string $admin_comment;
    private ?string $assistant_comment;

    public function __construct(
        int $user_id,
        int $selected_assistant_id,
        string $title,
        string $description,
        string $type,
        string $admin_validation_status = 'en_attente',
        string $assistant_validation_status = 'en_attente',
        string $status = 'soumis',
        ?string $admin_comment = null,
        ?string $assistant_comment = null,
        ?int $id = null
    ) {
        $this->user_id = $user_id;
        $this->selected_assistant_id = $selected_assistant_id;
        $this->title = $title;
        $this->description = $description;
        $this->type = $type;
        $this->admin_validation_status = $admin_validation_status;
        $this->assistant_validation_status = $assistant_validation_status;
        $this->status = $status;
        $this->admin_comment = $admin_comment;
        $this->assistant_comment = $assistant_comment;
        $this->id = $id;
    }

    // Getters and Setters ... (basic properties access)
    // Omitted boilerplate for brevity, will rely on Controller arrays or explicit getters when needed.

    public function getId(): ?int { return $this->id; }
    public function getUserId(): int { return $this->user_id; }
    public function getSelectedAssistantId(): int { return $this->selected_assistant_id; }
    public function getTitle(): string { return $this->title; }
    public function getDescription(): string { return $this->description; }
    public function getType(): string { return $this->type; }
    public function getAdminValidationStatus(): string { return $this->admin_validation_status; }
    public function getAssistantValidationStatus(): string { return $this->assistant_validation_status; }
    public function getStatus(): string { return $this->status; }
    public function getAdminComment(): ?string { return $this->admin_comment; }
    public function getAssistantComment(): ?string { return $this->assistant_comment; }
}
