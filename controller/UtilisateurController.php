<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../model/utilisateur.php';

class UtilisateurController
{
    private PDO $conn;

    private const ALLOWED_ROLES = ['admin', 'agent', 'client'];

    public function __construct(?PDO $connection = null)
    {
        if ($connection instanceof PDO) {
            $this->conn = $connection;
            return;
        }

        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO) {
            $this->conn = $GLOBALS['conn'];
            return;
        }

        if (class_exists('Config') && method_exists('Config', 'getConnexion')) {
            $configConnection = Config::getConnexion();
            if ($configConnection instanceof PDO) {
                $this->conn = $configConnection;
                return;
            }
        }

        throw new RuntimeException('Connexion base de donnees indisponible.');
    }

    public function addUser(string $nom, string $prenom, string $email, string $password, string $telephone, string $role): int
    {
        $data = $this->validatePayload([
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'password' => $password,
            'telephone' => $telephone,
            'role' => $role
        ], false);

        if ($this->emailExists($data['email'])) {
            throw new InvalidArgumentException('Un utilisateur existe deja avec cet e-mail.');
        }

        $utilisateur = $this->buildUtilisateurFromPayload($data);

        $sql = 'INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, telephone, role, statut_compte, date_creation)
                VALUES (:nom, :prenom, :email, :password, :telephone, :role, :statut_compte, :date_creation)';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':nom' => $utilisateur->getNom(),
            ':prenom' => $utilisateur->getPrenom(),
            ':email' => $utilisateur->getEmail(),
            ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
            ':telephone' => $utilisateur->getTelephone(),
            ':role' => $utilisateur->getRole(),
            ':statut_compte' => 'actif',
            ':date_creation' => date('Y-m-d')
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function getUsers(array $filters = []): array
    {
        $sql = 'SELECT id, nom, prenom, email, telephone, role, statut_compte, date_creation FROM utilisateur WHERE 1=1';
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (nom LIKE :search OR prenom LIKE :search OR email LIKE :search OR telephone LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $roleFilter = $this->normalizeRole((string) ($filters['role'] ?? ''));
        if ($roleFilter !== '' && $roleFilter !== 'tout' && in_array($roleFilter, self::ALLOWED_ROLES, true)) {
            $sql .= ' AND role = :role';
            $params[':role'] = $roleFilter;
        }

        $sql .= ' ORDER BY id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $row): array => $this->normalizeUserRow($row), $rows);
    }

    public function getUserById(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT id, nom, prenom, email, telephone, role, statut_compte, date_creation FROM utilisateur WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeUserRow($row) : null;
    }

    public function getUserByEmail(string $email): ?array
    {
        $stmt = $this->conn->prepare('SELECT id, nom, prenom, email, telephone, role, statut_compte, date_creation FROM utilisateur WHERE email = :email');
        $stmt->execute([':email' => trim(strtolower($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeUserRow($row) : null;
    }

    public function getUserEntityById(int $id): ?Utilisateur
    {
        $row = $this->getUserById($id);
        return $row ? $this->buildUtilisateurFromRow($row) : null;
    }

    public function authenticateUser(string $email, string $password): ?array
    {
        $stmt = $this->conn->prepare('SELECT id, nom, prenom, email, telephone, role, statut_compte, date_creation, mot_de_passe FROM utilisateur WHERE email = :email');
        $stmt->execute([':email' => trim(strtolower($email))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        if (strtolower((string) ($user['statut_compte'] ?? 'actif')) !== 'actif') {
            throw new RuntimeException('Ce compte est inactif ou bloque.');
        }

        $hash = (string) ($user['mot_de_passe'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return null;
        }

        unset($user['mot_de_passe']);
        return $this->normalizeUserRow($user);
    }

    public function updateUser(int $id, string $nom, string $prenom, string $email, string $telephone, string $role, ?string $password = null): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Identifiant utilisateur invalide.');
        }

        $data = $this->validatePayload([
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'password' => $password ?? '',
            'telephone' => $telephone,
            'role' => $role
        ], true);

        if ($this->emailExists($data['email'], $id)) {
            throw new InvalidArgumentException('Cet e-mail est deja utilise par un autre utilisateur.');
        }

        $utilisateur = $this->buildUtilisateurFromPayload($data, $id);

        $sql = 'UPDATE utilisateur
                SET nom = :nom,
                    prenom = :prenom,
                    email = :email,
                    telephone = :telephone,
                    role = :role';

        $params = [
            ':nom' => $utilisateur->getNom(),
            ':prenom' => $utilisateur->getPrenom(),
            ':email' => $utilisateur->getEmail(),
            ':telephone' => $utilisateur->getTelephone(),
            ':role' => $utilisateur->getRole(),
            ':id' => $id
        ];

        if ($data['password'] !== '') {
            $sql .= ', mot_de_passe = :password';
            $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $sql .= ' WHERE id = :id';

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteUser(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Identifiant utilisateur invalide.');
        }

        $stmt = $this->conn->prepare('DELETE FROM utilisateur WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    private function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM utilisateur WHERE email = :email';
        $params = [':email' => $email];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :excludeId';
            $params[':excludeId'] = $excludeId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function normalizeRole(string $role): string
    {
        $normalized = strtolower(trim($role));
        if ($normalized === 'user') {
            return 'client';
        }
        return $normalized;
    }

    private function buildUtilisateurFromPayload(array $payload, ?int $id = null): Utilisateur
    {
        return new Utilisateur(
            (string) ($payload['nom'] ?? ''),
            (string) ($payload['prenom'] ?? ''),
            (string) ($payload['email'] ?? ''),
            (string) ($payload['password'] ?? ''),
            (string) ($payload['telephone'] ?? ''),
            $this->normalizeRole((string) ($payload['role'] ?? 'client')),
            $id
        );
    }

    private function buildUtilisateurFromRow(array $row): Utilisateur
    {
        $entity = Utilisateur::fromDatabaseRow($row);
        $entity->setRole($this->normalizeRole($entity->getRole()));
        return $entity;
    }

    private function normalizeUserRow(array $row): array
    {
        $entity = $this->buildUtilisateurFromRow($row);

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'nom' => $entity->getNom(),
            'prenom' => $entity->getPrenom(),
            'email' => $entity->getEmail(),
            'telephone' => $entity->getTelephone(),
            'role' => $entity->getRole(),
            'statut_compte' => (string) ($row['statut_compte'] ?? 'actif'),
            'date_creation' => (string) ($row['date_creation'] ?? '')
        ];
    }

    private function validatePayload(array $payload, bool $isUpdate): array
    {
        $nom = trim((string) ($payload['nom'] ?? ''));
        $prenom = trim((string) ($payload['prenom'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $telephone = preg_replace('/\s+/', '', (string) ($payload['telephone'] ?? ''));
        $role = $this->normalizeRole((string) ($payload['role'] ?? ''));

        $errors = [];

        if (!preg_match('/^[\p{L}\s\'-]{2,60}$/u', $nom)) {
            $errors[] = 'Le nom doit contenir 2 a 60 caracteres alphabetiques.';
        }

        if (!preg_match('/^[\p{L}\s\'-]{2,60}$/u', $prenom)) {
            $errors[] = 'Le prenom doit contenir 2 a 60 caracteres alphabetiques.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adresse e-mail invalide.';
        }

        if (!preg_match('/^\+?[0-9]{8,15}$/', $telephone)) {
            $errors[] = 'Le telephone doit contenir entre 8 et 15 chiffres.';
        }

        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            $errors[] = 'Le role doit etre admin, agent ou client.';
        }

        if (!$isUpdate || $password !== '') {
            if (strlen($password) < 6) {
                $errors[] = 'Le mot de passe doit contenir au moins 6 caracteres.';
            }
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return [
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'password' => $password,
            'telephone' => $telephone,
            'role' => $role
        ];
    }
}
?>