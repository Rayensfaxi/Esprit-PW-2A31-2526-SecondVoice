<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../model/utilisateur.php';

class UtilisateurController
{
    private PDO $conn;
    private string $userTable;
    /** @var array<string, array{Field:string,Type:string}> */
    private array $columnMeta = [];

    private const ROLE_ALIAS_TO_STORAGE = [
        'client' => 'user',
        'agent' => 'assistant'
    ];

    private const ROLE_ALIAS_TO_APP = [
        'user' => 'client',
        'assistant' => 'agent'
    ];

    public function __construct(?PDO $connection = null)
    {
        if ($connection instanceof PDO) {
            $this->conn = $connection;
        } elseif (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO) {
            $this->conn = $GLOBALS['conn'];
        } elseif (class_exists('Config') && method_exists('Config', 'getConnexion')) {
            $configConnection = Config::getConnexion();
            if (!$configConnection instanceof PDO) {
                throw new RuntimeException('Connexion base de donnees indisponible.');
            }
            $this->conn = $configConnection;
        } else {
            throw new RuntimeException('Connexion base de donnees indisponible.');
        }

        $this->bootstrapUserTable();
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

        if ($this->hasColumn('email') && $this->emailExists((string) $data['email'])) {
            throw new InvalidArgumentException('Un utilisateur existe deja avec cet e-mail.');
        }

        $insert = [];
        if ($this->hasColumn('nom')) {
            $insert['nom'] = (string) $data['nom'];
        }
        if ($this->hasColumn('prenom')) {
            $insert['prenom'] = (string) $data['prenom'];
        }
        if ($this->hasColumn('email')) {
            $insert['email'] = (string) $data['email'];
        }
        if ($this->hasColumn('telephone')) {
            $insert['telephone'] = (string) $data['telephone'];
        }
        if ($this->hasColumn('role')) {
            $insert['role'] = (string) $data['role'];
        }
        if ($this->hasColumn('mot_de_passe')) {
            $insert['mot_de_passe'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }
        if ($this->hasColumn('statut_compte')) {
            $insert['statut_compte'] = 'actif';
        }
        if ($this->hasColumn('date_creation')) {
            $insert['date_creation'] = date('Y-m-d');
        }

        if ($insert === []) {
            throw new RuntimeException('Impossible de creer un utilisateur: schema incompatible.');
        }

        $columns = array_keys($insert);
        $placeholders = array_map(static fn(string $key): string => ':' . $key, $columns);

        $sql = 'INSERT INTO ' . $this->userTable . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->conn->prepare($sql);

        $params = [];
        foreach ($insert as $col => $value) {
            $params[':' . $col] = $value;
        }

        $stmt->execute($params);
        return (int) $this->conn->lastInsertId();
    }

    public function getUsers(array $filters = []): array
    {
        $selectColumns = $this->selectColumns(false);
        $sql = 'SELECT ' . implode(', ', $selectColumns) . ' FROM ' . $this->userTable . ' WHERE 1=1';
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));
        $searchable = array_values(array_filter(['nom', 'prenom', 'email', 'telephone'], fn(string $col): bool => $this->hasColumn($col)));
        if ($search !== '' && $searchable !== []) {
            $clauses = [];
            foreach ($searchable as $col) {
                $clauses[] = $col . ' LIKE :search';
            }
            $sql .= ' AND (' . implode(' OR ', $clauses) . ')';
            $params[':search'] = '%' . $search . '%';
        }

        $roleFilter = $this->normalizeRoleForStorage((string) ($filters['role'] ?? ''));
        if ($this->hasColumn('role') && $roleFilter !== '' && $roleFilter !== 'tout') {
            $sql .= ' AND role = :role';
            $params[':role'] = $roleFilter;
        }

        $orderBy = $this->hasColumn('id') ? 'id' : $selectColumns[0];
        $sql .= ' ORDER BY ' . $orderBy . ' DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $row): array => $this->normalizeUserRow($row), $rows);
    }

    public function getUserById(int $id): ?array
    {
        if (!$this->hasColumn('id')) {
            return null;
        }

        $sql = 'SELECT ' . implode(', ', $this->selectColumns(false)) . ' FROM ' . $this->userTable . ' WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeUserRow($row) : null;
    }

    public function getUserByEmail(string $email): ?array
    {
        if (!$this->hasColumn('email')) {
            return null;
        }

        $sql = 'SELECT ' . implode(', ', $this->selectColumns(false)) . ' FROM ' . $this->userTable . ' WHERE email = :email';
        $stmt = $this->conn->prepare($sql);
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
        if (!$this->hasColumn('email')) {
            return null;
        }

        $sql = 'SELECT ' . implode(', ', $this->selectColumns(true)) . ' FROM ' . $this->userTable . ' WHERE email = :email';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => trim(strtolower($email))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        if ($this->hasColumn('statut_compte')) {
            $status = strtolower((string) ($user['statut_compte'] ?? 'actif'));
            if (in_array($status, ['inactif', 'bloque', 'blocked', 'inactive'], true)) {
                throw new RuntimeException('Ce compte est inactif ou bloque.');
            }
        }

        if ($this->hasColumn('mot_de_passe')) {
            $hash = (string) ($user['mot_de_passe'] ?? '');
            if ($hash === '' || !password_verify($password, $hash)) {
                return null;
            }
        }

        unset($user['mot_de_passe']);
        return $this->normalizeUserRow($user);
    }

    public function updateUser(int $id, string $nom, string $prenom, string $email, string $telephone, string $role, ?string $password = null): bool
    {
        if ($id <= 0 || !$this->hasColumn('id')) {
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

        if ($this->hasColumn('email') && $this->emailExists((string) $data['email'], $id)) {
            throw new InvalidArgumentException('Cet e-mail est deja utilise par un autre utilisateur.');
        }

        $set = [];
        $params = [':id' => $id];

        if ($this->hasColumn('nom')) {
            $set[] = 'nom = :nom';
            $params[':nom'] = (string) $data['nom'];
        }
        if ($this->hasColumn('prenom')) {
            $set[] = 'prenom = :prenom';
            $params[':prenom'] = (string) $data['prenom'];
        }
        if ($this->hasColumn('email')) {
            $set[] = 'email = :email';
            $params[':email'] = (string) $data['email'];
        }
        if ($this->hasColumn('telephone')) {
            $set[] = 'telephone = :telephone';
            $params[':telephone'] = (string) $data['telephone'];
        }
        if ($this->hasColumn('role')) {
            $set[] = 'role = :role';
            $params[':role'] = (string) $data['role'];
        }

        if ($this->hasColumn('mot_de_passe') && (string) $data['password'] !== '') {
            $set[] = 'mot_de_passe = :password';
            $params[':password'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }

        if ($set === []) {
            return true;
        }

        $sql = 'UPDATE ' . $this->userTable . ' SET ' . implode(', ', $set) . ' WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteUser(int $id): bool
    {
        if ($id <= 0 || !$this->hasColumn('id')) {
            throw new InvalidArgumentException('Identifiant utilisateur invalide.');
        }

        $stmt = $this->conn->prepare('DELETE FROM ' . $this->userTable . ' WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    private function bootstrapUserTable(): void
    {
        foreach (['utilisateurs', 'utilisateur'] as $table) {
            if ($this->tableExists($table)) {
                $this->userTable = $table;
                $this->loadColumnMeta();
                return;
            }
        }

        throw new RuntimeException('Table utilisateur introuvable (utilisateurs/utilisateur).');
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->conn->prepare('SHOW TABLES LIKE :tableName');
        $stmt->execute([':tableName' => $tableName]);
        return (bool) $stmt->fetchColumn();
    }

    private function loadColumnMeta(): void
    {
        $stmt = $this->conn->query('SHOW COLUMNS FROM ' . $this->userTable);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $field = strtolower((string) ($row['Field'] ?? ''));
            if ($field !== '') {
                $this->columnMeta[$field] = [
                    'Field' => (string) ($row['Field'] ?? ''),
                    'Type' => (string) ($row['Type'] ?? '')
                ];
            }
        }
    }

    private function hasColumn(string $column): bool
    {
        return isset($this->columnMeta[strtolower($column)]);
    }

    private function selectColumns(bool $withPassword): array
    {
        $columns = ['id', 'nom', 'prenom', 'email', 'telephone', 'role', 'statut_compte', 'date_creation'];
        if ($withPassword) {
            $columns[] = 'mot_de_passe';
        }

        $selected = [];
        foreach ($columns as $col) {
            if ($this->hasColumn($col)) {
                $selected[] = $col;
            }
        }

        return $selected !== [] ? $selected : ['*'];
    }

    private function emailExists(string $email, ?int $excludeId = null): bool
    {
        if (!$this->hasColumn('email')) {
            return false;
        }

        $sql = 'SELECT id FROM ' . $this->userTable . ' WHERE email = :email';
        $params = [':email' => $email];

        if ($excludeId !== null && $this->hasColumn('id')) {
            $sql .= ' AND id <> :excludeId';
            $params[':excludeId'] = $excludeId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function normalizeRoleForStorage(string $role): string
    {
        $normalized = strtolower(trim($role));
        if ($normalized === '') {
            return $normalized;
        }

        return self::ROLE_ALIAS_TO_STORAGE[$normalized] ?? $normalized;
    }

    private function normalizeRoleForApp(string $role): string
    {
        $normalized = strtolower(trim($role));
        if ($normalized === '') {
            return 'client';
        }

        return self::ROLE_ALIAS_TO_APP[$normalized] ?? $normalized;
    }

    private function roleAllowedBySchema(string $role): bool
    {
        if (!$this->hasColumn('role')) {
            return true;
        }

        $type = strtolower((string) ($this->columnMeta['role']['Type'] ?? ''));
        if (!str_starts_with($type, 'enum(')) {
            return true;
        }

        preg_match_all("/'([^']+)'/", $type, $matches);
        $allowed = $matches[1] ?? [];
        if ($allowed === []) {
            return true;
        }

        return in_array($role, $allowed, true);
    }

    private function resolveRoleForStorage(string $role): string
    {
        $normalized = $this->normalizeRoleForStorage($role);
        if ($normalized === '') {
            throw new InvalidArgumentException('Le role est obligatoire.');
        }

        if ($this->roleAllowedBySchema($normalized)) {
            return $normalized;
        }

        if ($normalized === 'assistant' && $this->roleAllowedBySchema('agent')) {
            return 'agent';
        }

        if ($normalized === 'user' && $this->roleAllowedBySchema('client')) {
            return 'client';
        }

        throw new InvalidArgumentException('Role non supporte par la base de donnees.');
    }

    private function buildUtilisateurFromPayload(array $payload, ?int $id = null): Utilisateur
    {
        return new Utilisateur(
            (string) ($payload['nom'] ?? ''),
            (string) ($payload['prenom'] ?? ''),
            (string) ($payload['email'] ?? ''),
            (string) ($payload['password'] ?? ''),
            (string) ($payload['telephone'] ?? ''),
            $this->normalizeRoleForApp((string) ($payload['role'] ?? 'client')),
            $id
        );
    }

    private function buildUtilisateurFromRow(array $row): Utilisateur
    {
        $entity = Utilisateur::fromDatabaseRow($row);
        $entity->setRole($this->normalizeRoleForApp($entity->getRole()));
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
        $role = $this->resolveRoleForStorage((string) ($payload['role'] ?? ''));

        $errors = [];

        if ($this->hasColumn('nom') && !preg_match('/^[\p{L}\s\'-]{2,60}$/u', $nom)) {
            $errors[] = 'Le nom doit contenir 2 a 60 caracteres alphabetiques.';
        }

        if ($this->hasColumn('prenom') && $prenom !== '' && !preg_match('/^[\p{L}\s\'-]{2,60}$/u', $prenom)) {
            $errors[] = 'Le prenom doit contenir 2 a 60 caracteres alphabetiques.';
        }

        if ($this->hasColumn('email') && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adresse e-mail invalide.';
        }

        if ($this->hasColumn('telephone') && $telephone !== '' && !preg_match('/^\+?[0-9]{8,15}$/', $telephone)) {
            $errors[] = 'Le telephone doit contenir entre 8 et 15 chiffres.';
        }

        if ($this->hasColumn('mot_de_passe')) {
            if (!$isUpdate || $password !== '') {
                if (strlen($password) < 6) {
                    $errors[] = 'Le mot de passe doit contenir au moins 6 caracteres.';
                }
            }
        } else {
            $password = '';
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
