<?php
require_once __DIR__ . '/../config.php';

class Guide
{
    private ?int $id = null;
    private int $goal_id;
    private string $title;
    private string $content;

    public function __construct(int $goal_id, string $title, string $content, ?int $id = null)
    {
        $this->goal_id = $goal_id;
        $this->title = $title;
        $this->content = $content;
        $this->id = $id;
    }

    public function getId(): ?int { return $this->id; }
    public function getGoalId(): int { return $this->goal_id; }
    public function getTitle(): string { return $this->title; }
    public function getContent(): string { return $this->content; }

    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function setGoalId(int $goal_id): self { $this->goal_id = $goal_id; return $this; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function setContent(string $content): self { $this->content = $content; return $this; }
}
