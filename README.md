# SecondVoice

Plateforme web d'accompagnement citoyen développée en PHP (XAMPP), organisée selon une architecture **MVC** avec séparation Front Office / Back Office.

## 1. Objectif du projet

SecondVoice permet de :
- créer et suivre des demandes d'accompagnement,
- gérer des guides/étapes de suivi,
- administrer les utilisateurs et modules de gestion,
- proposer des interfaces dédiées selon le rôle (client, agent, admin).

## 2. Stack technique

- PHP 
- MariaDB / MySQL
- XAMPP (Apache + MySQL)
- HTML/CSS/JavaScript 

## 3. Architecture (MVC)

```text
Second voice/
  config.php                    # configuration centrale (DB, paramètres)
  controller/                   # logique applicative et contrôleurs
  model/                        # entités et accès métier
  view/                         # vues (frontoffice / backoffice)
    frontoffice/
    backoffice/
    partials/                   # composants UI réutilisables
  lib/                          # bibliothèques locales du projet
  storage/                      # fichiers générés / données locales
```

### Règles de séparation appliquées

- **Model** : contient les entités et la logique métier réutilisable.
- **Controller** : traite les actions (validation, orchestration, redirections).
- **View** : contient l'affichage et les templates (pas de logique métier lourde).

## 4. Rôles et accès

- **Client** : création/suivi de demandes d'accompagnement.
- **Agent (Assistant)** : traitement des missions et gestion des guides.
- **Admin** : supervision globale et modération Back Office.

## 5. Services disponibles (Front Office)

- **Accompagnement professionnel** (`service-accompagnement.php`)
- **Brainstorming / idées** (`service-brainstorming.php`)
- **Soumission brainstorming** (`service-brainstorming-submit.php`)
- **Demande administrative** (`service-demande-administrative.php`)
- **Événements** (`service-evenements.php`)
- **Rendez-vous / prise de rendez-vous** (`service-rendezvous.php`, `service-prise-rendezvous.php`)
- **Réclamations** (`service-reclamations.php`)
- **Suivi de dossiers** (`service-suivi-dossiers.php`)

## 6. Modules de gestion (Back Office)

- Gestion des utilisateurs
- Gestion des accompagnements et guides
- Gestion des brainstormings / idées
- Gestion des événements
- Gestion des rendez-vous
- Gestion des réclamations
- Gestion des documents

## 7. Installation locale (XAMPP)

1. Placer le projet dans :
   `C:\xampp\htdocs\Second voice\Second voice`
2. Démarrer **Apache** et **MySQL**.
3. Créer la base de données dans phpMyAdmin.
4. Configurer la connexion dans `config.php`.
5. Ouvrir l'application depuis le navigateur :
   `http://localhost/Second%20voice/Second%20voice/view/frontoffice/index.php`

## 8. Conventions de qualité

- Centralisation des paramètres sensibles dans `config.php`.
- Réutilisation de composants via `view/partials`.
- Contrôle d'accès selon le rôle utilisateur.
- Organisation des ressources statiques sous `view/.../assets`.

## 9. Points d'évaluation académique

Le projet est structuré pour démontrer :
- une organisation MVC lisible,
- une séparation front/back claire,
- une gestion des rôles,
- un flux fonctionnel complet sur le module d'accompagnement.

## 10. Auteurs

Projet académique réalisé dans le cadre d'un module de développement web.
