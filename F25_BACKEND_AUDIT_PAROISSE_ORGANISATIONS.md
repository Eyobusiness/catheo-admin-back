# CATHEO — AUDIT TECHNIQUE BACKEND (F25)
## Liaison Paroisse ↔ Organisations ↔ Produits ↔ Abonnements

> **Type de document** : Rapport d'audit technique d'architecture backend (100% lecture & analyse)  
> **Date de réalisation** : 24 septembre 2026  
> **Statut backend** : Aucun fichier modifié, aucune migration exécutée, intégrité 100% préservée  

---

## SOMMAIRE EXÉCUTIF

Le présent audit a été réalisé sur le backend Laravel du projet **CATHEO** dans le but de certifier si l'architecture actuelle de la base de données, des modèles Eloquent, des services métiers et des routes API permet d'implémenter le workflow cible pour le Super Admin :
1. **Une paroisse liée à plusieurs organisations (OPPE, OPPJ, OPPA)** : **TOTALEMENT SUPPORTÉ** au niveau BDD et Eloquent.
2. **Organisation indépendante (sans paroisse)** : **STRICTEMENT IMPOSSIBLE DANS LE SCHÉMA ACTUEL** (colonne `paroisse_configuration_id` NOT NULL dans la table `organisations`).
3. **Abonnements distincts entre Paroisses et Organisations** : **NON SUPPORTÉ DANS LE SCHÉMA ACTUEL** (la table `abonnements` n'a pas de colonne `organisation_id` et exige `paroisse_configuration_id` NOT NULL).
4. **Disponibilité des endpoints Super Admin** :
   - Lecture Paroisses / Organisations / Produits / Abonnements : **DISPONIBLE**
   - Création / Modification / Suppression d'Organisations via API Super Admin : **NON IMPLÉMENTÉ** (aucune route `POST/PUT/DELETE /api/v1/super-admin/organisations`).

---

## PARTIE 1 — ARCHITECTURE ACTUELLE DE LA BASE DE DONNÉES

### 1. Table `paroisse_configurations` (Désignée fonctionnellement comme Paroisses / Catéchèse)
*Note : Le modèle Eloquent est [CatecheseConfiguration.php](file:///c:/xampp/htdocs/catheo/app/Models/CatecheseConfiguration.php) qui pointe explicitement sur `protected $table = 'paroisse_configurations';`.*
*Migrations : `2026_01_01_000001`, `2026_08_24_000001`, `2026_08_30_000002`.*

- **PK** : `id` (`bigint unsigned`, auto_increment)
- **UUID** : `uuid` (`char(36)`, non null, contrainte UNIQUE)
- **Clés étrangères** : Aucune
- **Colonnes importantes** :
  - `nom_paroisse` (varchar 255 - anciennement `nom`)
  - `code_paroisse` (varchar 255, contrainte UNIQUE)
  - `prefixe_matricule` (varchar 20, nullable)
  - `prefixe_recu` (varchar 20, nullable)
  - `diocese`, `doyenne`, `ville`, `commune`, `telephone`, `email`, `site_web`, `adresse`
  - `logo_paroisse` (varchar 255, nullable)
  - `logo_catechese` (varchar 255, nullable)
  - `logo_path` (varchar 255, nullable, conservé pour rétrocompatibilité)
  - `cure_nom`, `coordination_nom`
  - `statut` (varchar 255, default `'actif'`)
  - `created_at`, `updated_at`, `deleted_at` (SoftDeletes)
  - Audit : `created_by`, `updated_by`, `deleted_by` (uuid)
- **Contraintes uniques** :
  - `paroisse_configurations_uuid_unique` sur `uuid`
  - `paroisse_configurations_code_paroisse_unique` sur `code_paroisse`
- **Index** :
  - `PRIMARY KEY (id)`
  - `UNIQUE (uuid)`
  - `UNIQUE (code_paroisse)`

---

### 2. Table `produits`
*Modèle Eloquent : [Produit.php](file:///c:/xampp/htdocs/catheo/app/Models/Produit.php)*
*Migration : `2026_09_18_140000_create_produits_table.php`*

- **PK** : `id` (`bigint unsigned`, auto_increment)
- **UUID** : `uuid` (`char(36)`, non null, contrainte UNIQUE)
- **Clés étrangères** : Aucune
- **Colonnes importantes** :
  - `code` (varchar 255, contrainte UNIQUE) : Constantes `CATHEO`, `OPPE`, `OPPJ`, `OPPA`
  - `nom` (varchar 255)
  - `description` (text, nullable)
  - `icone` (varchar 255, nullable)
  - `statut` (varchar 255, default `'actif'`)
  - Timestamps, SoftDeletes (`deleted_at`), Audit (`created_by`, `updated_by`, `deleted_by`)
- **Contraintes uniques** :
  - `produits_uuid_unique` sur `uuid`
  - `produits_code_unique` sur `code`
- **Index** :
  - `PRIMARY KEY (id)`
  - `UNIQUE (uuid)`
  - `UNIQUE (code)`

---

### 3. Table `organisations`
*Modèle Eloquent : [Organisation.php](file:///c:/xampp/htdocs/catheo/app/Models/Organisation.php)*
*Migration : `2026_09_18_140100_create_organisations_table.php`*

- **PK** : `id` (`bigint unsigned`, auto_increment)
- **UUID** : `uuid` (`char(36)`, non null, contrainte UNIQUE)
- **Clés étrangères** :
  - `paroisse_configuration_id` : `foreignId` référençant `paroisse_configurations(id)` avec `cascadeOnDelete()` (**NOT NULL**)
  - `produit_id` : `foreignId` référençant `produits(id)` avec `restrictOnDelete()` (**NOT NULL**)
- **Colonnes importantes** :
  - `type_organisation` (`string(50)`, non null) : valeurs autorisées `OPPE`, `OPPJ`, `OPPA`
  - `code` (`string(100)`, nullable)
  - `nom` (`string(255)`, non null)
  - `description` (`text`, nullable)
  - `logo_path` (`string(255)`, nullable)
  - `telephone`, `email`, `adresse`
  - `responsable_nom`, `responsable_telephone`, `responsable_email`
  - `statut` (`string(50)`, default `'actif'`)
  - `date_activation` (`date`, nullable), `date_desactivation` (`date`, nullable)
  - Timestamps, SoftDeletes (`deleted_at`), Audit (`created_by`, `updated_by`, `deleted_by`)
- **Contraintes uniques** :
  - `organisations_uuid_unique` sur `uuid`
  - **Unicité conditionnelle active** :
    - Sur MySQL : colonne virtuelle générée `active_unique_key` = `IF(deleted_at IS NULL, CONCAT(paroisse_configuration_id, '_', type_organisation), NULL)` avec index UNIQUE `organisations_paroisse_type_active_unique` (`active_unique_key`).
    - Sur SQLite (tests) : `CREATE UNIQUE INDEX organisations_paroisse_type_active_unique ON organisations(paroisse_configuration_id, type_organisation) WHERE deleted_at IS NULL`.
- **Index** :
  - `PRIMARY KEY (id)`
  - `UNIQUE (uuid)`
  - `UNIQUE (active_unique_key)` (ou index conditionnel SQLite)
  - Index composite `org_paroisse_statut_idx` sur `(paroisse_configuration_id, statut)`

---

### 4. Table `formules`
*Modèle Eloquent : [Formule.php](file:///c:/xampp/htdocs/catheo/app/Models/Formule.php)*
*Migration : `2026_09_18_150000_create_formules_table.php`*

- **PK** : `id` (`bigint unsigned`, auto_increment)
- **UUID** : `uuid` (`char(36)`, non null, contrainte UNIQUE)
- **Clés étrangères** :
  - `produit_id` : `foreignId` référençant `produits(id)` avec `cascadeOnDelete()` (**NOT NULL**)
- **Colonnes importantes** :
  - `code` (`string(50)`) : ex. `STANDARD`, `PRO`, `PREMIUM`, `GRATUIT`
  - `nom` (`string(255)`)
  - `description` (`text`, nullable)
  - `periodicite` (`string(20)`, default `'annuelle'`) : `'mensuelle'`, `'annuelle'`
  - `montant` (`decimal(12,2)`, default `0.00`)
  - `devise` (`string(10)`, default `'XOF'`)
  - `est_gratuite` (`boolean`, default `false`)
  - `statut` (`string(20)`, default `'actif'`)
  - `ordre` (`integer`, default `0`)
  - Timestamps, SoftDeletes, Audit
- **Contraintes uniques** : `formules_uuid_unique` sur `uuid`
- **Index** :
  - `PRIMARY KEY (id)`
  - `UNIQUE (uuid)`
  - Index composite `formules_produit_statut_idx` sur `(produit_id, statut)`

---

### 5. Table `abonnements`
*Modèle Eloquent : [Abonnement.php](file:///c:/xampp/htdocs/catheo/app/Models/Abonnement.php)*
*Migration : `2026_09_18_150100_create_abonnements_table.php`*

- **PK** : `id` (`bigint unsigned`, auto_increment)
- **UUID** : `uuid` (`char(36)`, non null, contrainte UNIQUE)
- **Clés étrangères** :
  - `paroisse_configuration_id` : `foreignId` référençant `paroisse_configurations(id)` avec `cascadeOnDelete()` (**NOT NULL**)
  - `formule_id` : `foreignId` référençant `formules(id)` avec `restrictOnDelete()` (**NOT NULL**)
  - **ATTENTION** : **AUCUNE CLÉ ÉTRANGÈRE `organisation_id`** n'existe dans cette table !
- **Colonnes importantes** :
  - `reference` (`string(50)`, non null, contrainte UNIQUE, ex: `ABO-2026-XXXX`)
  - `date_debut` (`date`, non null)
  - `date_fin` (`date`, nullable)
  - `statut` (`string(30)`, default `'en_attente'`) : `'en_attente'`, `'actif'`, `'suspendu'`, `'expire'`, `'resilie'`
  - `montant` (`decimal(12,2)`, snapshot figé au moment de la souscription)
  - `devise` (`string(10)`, default `'XOF'`)
  - `renouvellement_automatique` (`boolean`, default `true`)
  - `date_resiliation` (`date`, nullable), `motif_resiliation` (`text`, nullable), `observation` (`text`, nullable)
  - Timestamps, SoftDeletes, Audit
- **Contraintes uniques** :
  - `abonnements_uuid_unique` sur `uuid`
  - `abonnements_reference_unique` sur `reference`
- **Index** :
  - `PRIMARY KEY (id)`
  - `UNIQUE (uuid)`
  - `UNIQUE (reference)`
  - Index composite `abos_paroisse_statut_idx` sur `(paroisse_configuration_id, statut)`

---

### 6. Table `users`
*Modèle Eloquent : [User.php](file:///c:/xampp/htdocs/catheo/app/Models/User.php)*
*Migrations : `0001_01_01_000000`, `2026_01_01_000003`, `2026_09_18_140200`*

- **PK** : `id` (`bigint unsigned`, auto_increment)
- **UUID** : `uuid` (`char(36)`, non null, contrainte UNIQUE)
- **Clés étrangères** :
  - `paroisse_configuration_id` : nullable, référençant `paroisse_configurations(id)` avec `nullOnDelete()`
  - `profil_id` : nullable, référençant `profils(id)` avec `nullOnDelete()`
  - `organisation_id` : nullable, référençant `organisations(id)` avec `nullOnDelete()`
- **Colonnes importantes** :
  - `name` (string)
  - `email` (string, contrainte UNIQUE)
  - `telephone` (string, nullable)
  - `password` (string, hashed)
  - `user_type` (string, default `'admin'`) : `'super_admin'`, `'admin'`, `'animateur'`, `'parent'`, `'utilisateur'`
  - `username` (string, unique, nullable) : matricule catéchumène ou code
  - `statut` (string, default `'actif'`)
  - `dernier_login_at` (datetime, nullable)
  - Timestamps, SoftDeletes (`deleted_at`)
- **Contraintes uniques** :
  - `users_uuid_unique` sur `uuid`
  - `users_email_unique` sur `email`
  - `users_username_unique` sur `username`
- **Index** :
  - `PRIMARY KEY (id)`
  - `UNIQUE (uuid)`, `UNIQUE (email)`, `UNIQUE (username)`

---

## PARTIE 2 — RELATION ORGANISATION ↔ PAROISSE

### 2.1 Une organisation appartient-elle déjà à une paroisse ?
- **OUI, OBLIGATOIREMENT**.
- **Preuve schéma SQL** : Dans `2026_09_18_140100_create_organisations_table.php` (L. 18-20) :
  ```php
  $table->foreignId('paroisse_configuration_id')
      ->constrained('paroisse_configurations')
      ->cascadeOnDelete();
  ```
  La colonne est **NOT NULL**. Il est physiquement impossible en SQL d'insérer une ligne sans `paroisse_configuration_id`.
- **Preuve Eloquent** :
  - Dans [Organisation.php](file:///c:/xampp/htdocs/catheo/app/Models/Organisation.php#L93-L96) :
    ```php
    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }
    ```
  - Dans [CatecheseConfiguration.php](file:///c:/xampp/htdocs/catheo/app/Models/CatecheseConfiguration.php#L147-L150) :
    ```php
    public function organisations(): HasMany
    {
        return $this->hasMany(Organisation::class, 'paroisse_configuration_id');
    }
    ```

### 2.2 Peut-on créer plusieurs organisations pour une même paroisse ?
- **OUI, TOTALEMENT**.
- **Exemple attendu** : Paroisse Saint Michel avec OPPE, OPPJ et OPPA.
- **Vérification des contraintes** :
  - La contrainte d'unicité en base de données et dans Eloquent porte sur le couple `(paroisse_configuration_id, type_organisation)`.
  - Comme `type_organisation` vaut respectivement `'OPPE'`, `'OPPJ'` et `'OPPA'`, ces 3 organisations ont des signatures distinctes pour la même paroisse :
    - `1_OPPE`
    - `1_OPPJ`
    - `1_OPPA`
  - Ce comportement est d'ailleurs couvert et validé par le test unitaire existant [SaasCoreTest.php](file:///c:/xampp/htdocs/catheo/tests/Feature/SaasCoreTest.php#L153-L180) (`test_paroisse_can_have_oppe_oppj_oppa_simultaneously`).

### 2.3 Existe-t-il déjà une contrainte empêchant deux OPPE sur une même paroisse ?
- **OUI, VERROUILLAGE TOTAL SUR 2 NIVEAUX (Applicatif + BDD)**.
- **Exemple** : Vouloir créer deux OPPE sur Saint Michel (doublon).
- **Vérification technique** :
  1. **Niveau Applicatif (Modèle Eloquent)** : Dans [Organisation.php](file:///c:/xampp/htdocs/catheo/app/Models/Organisation.php#L55-L76) :
     ```php
     static::saving(function (Organisation $organisation) {
         $type = strtoupper(trim((string) $organisation->type_organisation));
         $existingQuery = static::where('paroisse_configuration_id', $organisation->paroisse_configuration_id)
             ->where('type_organisation', $type);
         if ($organisation->exists) {
             $existingQuery->where('id', '!=', $organisation->id);
         }
         if ($existingQuery->exists()) {
             throw new InvalidArgumentException("Une organisation active de type [{$type}] existe déjà pour cette paroisse.");
         }
     });
     ```
  2. **Niveau Base de Données (Index Unique)** :
     - MySQL : Colonne virtuelle `active_unique_key` avec index `UNIQUE organisations_paroisse_type_active_unique`.
     - SQLite : Index unique partiel `WHERE deleted_at IS NULL`.
- **Résultat** : Un tel doublon est **rigoureusement rejeté**. En revanche, si une organisation est supprimée (SoftDeletes), la contrainte se libère et permet la recréation.

---

## PARTIE 3 — VÉRIFICATION DES PRODUITS

Aujourd'hui, 4 produits sont définis dans la plateforme ([Produit.php](file:///c:/xampp/htdocs/catheo/app/Models/Produit.php#L18-L28)) :
- `CATHEO`
- `OPPE`
- `OPPJ`
- `OPPA`

### Produit ↔ Organisation :
- **Existe-t-il `produit_id` ?** : **OUI**. La table `organisations` a la colonne `produit_id` (`foreignId` référençant `produits(id)` avec `restrictOnDelete()`).
- **Existe-t-il une relation Eloquent ?** : **OUI**.
  - Dans [Organisation.php](file:///c:/xampp/htdocs/catheo/app/Models/Organisation.php#L101-L104) :
    ```php
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }
    ```
  - Dans [Produit.php](file:///c:/xampp/htdocs/catheo/app/Models/Produit.php#L61-L64) :
    ```php
    public function organisations(): HasMany
    {
        return $this->hasMany(Organisation::class, 'produit_id');
    }
    ```
- **Validation** : Dans [Organisation.php](file:///c:/xampp/htdocs/catheo/app/Models/Organisation.php#L61-L63), le `type_organisation` doit obligatoirement être l'un des types autorisés (`OPPE`, `OPPJ`, `OPPA`).
- **Réponse à la question : Une organisation est-elle déjà liée à un produit ?**
  - **OUI, STRICTEMENT OBLIGATOIRE**. La colonne `produit_id` est non-nullable en BDD. Chaque organisation créée pointe obligatoirement vers un enregistrement de la table `produits`.

---

## PARTIE 4 — VÉRIFICATION DES ENDPOINTS EXISTANTS

Voici le relevé exhaustif des routes **réellement déclarées** dans [routes/api.php](file:///c:/xampp/htdocs/catheo/routes/api.php) :

### 1. Paroisses
| Opération | Statut Réel | Route Exacte | Contrôleur / Méthode |
| :--- | :---: | :--- | :--- |
| **GET liste** | ✅ **Existe** | `GET /api/v1/super-admin/paroisses` | `SuperAdminParoisseController@index` |
| **GET détail** | ✅ **Existe** | `GET /api/v1/super-admin/paroisses/{id}` | `SuperAdminParoisseController@show` |
| **PUT modification** | ❌ **Inexistant en Super Admin** | *Non déclarée dans `/super-admin/`* *(Existe uniquement pour la paroisse connectée : `PUT /api/v1/paroisse-configuration`)* | `CatecheseConfigurationController@update` |
| **Création** | ❌ **Inexistant** | *Aucune route `POST /api/v1/super-admin/paroisses`* | — |
| **Suppression** | ❌ **Inexistant** | *Aucune route `DELETE /api/v1/super-admin/paroisses/{id}`* | — |

### 2. Organisations
| Opération | Statut Réel | Route Exacte | Contrôleur / Méthode |
| :--- | :---: | :--- | :--- |
| **GET liste** | ✅ **Existe** | `GET /api/v1/super-admin/organisations` | `SuperAdminOrganisationController@index` *(Filtres : `type_organisation`, `statut`, `paroisse_id`)* |
| **GET détail** | ✅ **Existe** | `GET /api/v1/super-admin/organisations/{organisation}` | `SuperAdminOrganisationController@show` |
| **Création** | ❌ **Inexistant en Super Admin** | *Aucune route `POST /api/v1/super-admin/organisations`* | — *(Dans les tests, les organisations étaient créées directement via Model Eloquent)* |
| **Modification** | ❌ **Inexistant en Super Admin** | *Aucune route `PUT /api/v1/super-admin/organisations/{organisation}`* *(Existe uniquement pour l'organisation connectée : `PUT /api/v1/organisation/info`)* | `OrganisationProfileController@update` |
| **Suppression** | ❌ **Inexistant** | *Aucune route `DELETE /api/v1/super-admin/organisations/{organisation}`* | — |

### 3. Responsable
| Opération | Statut Réel | Route Exacte | Contrôleur / Méthode |
| :--- | :---: | :--- | :--- |
| **Création premier responsable** | ✅ **Existe** | `POST /api/v1/super-admin/organisations/{organisation}/responsable` | `SuperAdminOrganisationController@createResponsable` |

### 4. Produits
| Opération | Statut Réel | Route Exacte | Contrôleur / Méthode |
| :--- | :---: | :--- | :--- |
| **GET liste** | ✅ **Existe** | `GET /api/v1/super-admin/produits` | `SuperAdminProduitController@index` |
| **GET détail** | ✅ **Existe** | `GET /api/v1/super-admin/produits/{produit}` | `SuperAdminProduitController@show` |
| *Bonus gestion* | ✅ **Existe** | `POST`, `PUT`, `PATCH toggle-status`, `DELETE` sur `/api/v1/super-admin/produits` | `SuperAdminProduitController` |

### 5. Abonnements
| Opération | Statut Réel | Route Exacte | Contrôleur / Méthode |
| :--- | :---: | :--- | :--- |
| **Création (souscription)** | ✅ **Existe** | `POST /api/v1/super-admin/abonnements` | `SuperAdminAbonnementController@store` |
| **GET détail** | ✅ **Existe** | `GET /api/v1/super-admin/abonnements/{abonnement}` | `SuperAdminAbonnementController@show` |
| **Changement de statut** | ✅ **Existe** | `PATCH /api/v1/super-admin/abonnements/{abonnement}/statut` | `SuperAdminAbonnementController@changerStatut` |
| *Bonus résiliation* | ✅ **Existe** | `POST /api/v1/super-admin/abonnements/{abonnement}/resilier` | `SuperAdminAbonnementController@resilier` |

---

## PARTIE 5 — VÉRIFICATION DES ABONNEMENTS

### Constat d'architecture :
Dans le backend actuel, la table `abonnements` est liée à :
- **UNE PAROISSE UNIQUEMENT**.
- **La colonne utilisée est STRICTEMENT : `paroisse_configuration_id`**.

### Détails du schéma et du code :
1. **Migration SQL** ([2026_09_18_150100_create_abonnements_table.php](file:///c:/xampp/htdocs/catheo/database/migrations/2026_09_18_150100_create_abonnements_table.php#L17-L18)) :
   ```php
   $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
   $table->foreignId('formule_id')->constrained('formules')->restrictOnDelete();
   ```
2. **Modèle [Abonnement.php](file:///c:/xampp/htdocs/catheo/app/Models/Abonnement.php#L65-L73)** :
   ```php
   public function paroisse(): BelongsTo
   {
       return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
   }

   public function formule(): BelongsTo
   {
       return $this->belongsTo(Formule::class, 'formule_id');
   }
   ```
3. **Form Request de validation** ([StoreAbonnementRequest.php](file:///c:/xampp/htdocs/catheo/app/Http/Requests/Api/V1/SuperAdmin/StoreAbonnementRequest.php#L33-L34)) :
   ```php
   'paroisse_configuration_id' => 'required|exists:paroisse_configurations,id',
   'formule_id'                => 'required|exists:formules,id',
   ```
4. **Conséquence métier** :
   - Comment une organisation est-elle couverte aujourd'hui ?
     Dans l'architecture conçue à l'Étape 1 SaaS, c'est **la Paroisse** qui souscrit à une formule du produit `OPPE`, `OPPJ` ou `OPPA`.
   - Il n'existe **AUCUN LIEN DIRECT** entre `abonnements` et `organisations`.
   - Si le métier exige que les abonnements des paroisses soient séparés des abonnements des organisations, ou qu'une organisation indépendante puisse souscrire un abonnement sans paroisse, **le schéma actuel ne le permet pas sans ajout d'une colonne `organisation_id` (nullable) dans `abonnements` et sans rendre `paroisse_configuration_id` nullable**.

---

## PARTIE 6 — VÉRIFICATION DE LA LOGIQUE MÉTIER ACTUELLE

### Cas A : Créer une paroisse, puis créer OPPE, OPPJ, OPPA rattachés à cette paroisse
- **Est-ce possible aujourd'hui ?**
  - **En Base de données / Modèle Eloquent** : **OUI, 100% POSSIBLE**.
    - Le schéma est spécifiquement pensé pour ce cas : `paroisse_configuration_id` pointe sur la paroisse, `type_organisation` accepte `OPPE`, `OPPJ`, `OPPA`, et la clé d'unicité `(paroisse_configuration_id, type_organisation)` valide la coexistence des 3 types pour une paroisse donnée.
  - **Via l'API Super Admin** : **NON, CAR LES ROUTES DE CRÉATION MANQUENT**.
    - Il n'y a pas d'endpoint `POST /api/v1/super-admin/organisations` ni `POST /api/v1/super-admin/paroisses`.

### Cas B : Créer uniquement OPPJ sans CATHEO, sans paroisse CATHEO
- **Est-ce possible aujourd'hui ?**
  - **NON, STRICTEMENT IMPOSSIBLE**.
- **Pourquoi ?**
  1. **Violation SQL** : La colonne `organisations.paroisse_configuration_id` est déclarée **NOT NULL** avec une contrainte de clé étrangère vers `paroisse_configurations(id)`. Toute tentative d'insérer une organisation sans paroisse génère une erreur SQL fatale : `SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'paroisse_configuration_id' cannot be null`.
  2. **Contrôle d'accès & Middleware** : Le middleware multi-tenant [EnsureOrganisationContext.php](file:///c:/xampp/htdocs/catheo/app/Http/Middleware/EnsureOrganisationContext.php) et le service de sécurité [SecurityContextService.php](file:///c:/xampp/htdocs/catheo/app/Services/SecurityContextService.php#L91-L96) vérifient impérativement l'intégrité `paroisse_configuration_id` entre l'utilisateur et son organisation (`verifyUserOrganisationIntegrity`).
  3. **Création du responsable** : L'endpoint `POST /super-admin/organisations/{org}/responsable` affecte automatiquement `'paroisse_configuration_id' => $organisation->paroisse_configuration_id` à l'utilisateur responsable créé.
  4. **Abonnement** : L'abonnement requiert obligatoirement un `paroisse_configuration_id`. Une organisation sans paroisse ne pourrait souscrire aucun abonnement.

### Cas C : Créer OPPE indépendant, sans synchronisation CATHEO
- **Est-ce possible aujourd'hui ?**
  - **Rattaché à une paroisse mais sans synchroniser la catéchèse CATHEO** : **OUI, PARFAITEMENT POSSIBLE**.
    - L'organisation dispose de ses tables propres et autonomes : `membres`, `activites`, `campagne_pelerinages`, `operation_organisations`.
    - La synchronisation avec CATHEO est un service optionnel sur demande (`GET /api/v1/organisation/catheo/population`). L'organisation peut vivre sa vie de façon 100% autonome et gérer ses membres manuellement.
  - **Totalement indépendant sans aucune paroisse hôte** : **NON, IMPOSSIBLE** (pour les mêmes raisons que le Cas B).

---

## PARTIE 7 — VÉRIFICATION DE LA SYNCHRONISATION CATHEO

### 7.1 Mécanisme de récupération de la population OPPE
La logique est implémentée dans la classe de service :
[app/Services/Organisation/CatheoPopulationService.php](file:///c:/xampp/htdocs/catheo/app/Services/Organisation/CatheoPopulationService.php)

Pour extraire la population d'une organisation, le service s'appuie conjointement sur :
1. **L'identifiant de la paroisse** : `$organisation->paroisse_configuration_id`.
2. **L'année pastorale courante de cette paroisse** : `AnneeCatechese::getAnneeCourante($paroisseId)` (les inscriptions des années antérieures sont strictement exclues).
3. **Les codes canoniques stricts de section** :
   - **OPPE** : `SEC-ENFANTS-PRI` (Primaire) et `SEC-ENFANTS-COL` (Collège)
   - **OPPJ** : `SEC-JEUNES` (Jeunesse)
   - **OPPA** : `SEC-ADULTES` (Adultes)
4. **Requête Eloquent exécutée** :
   ```php
   InscriptionAnnuelle::with(['catechumene', 'section', 'niveau', 'classe', 'anneeCatechese'])
       ->where('paroisse_configuration_id', $paroisseId)
       ->where('annee_catechese_id', $anneeCourante->id)
       ->whereHas('section', function ($q) use ($targetCodes) {
           $q->whereIn('code', $targetCodes);
       })
       ->latest('id');
   ```

### 7.2 Contrôleurs et usages
- Endpoint HTTP : `GET /api/v1/organisation/catheo/population` piloté par [CatheoPopulationController.php](file:///c:/xampp/htdocs/catheo/app/Http/Controllers/Api/V1/Organisation/CatheoPopulationController.php).
- Également utilisé dans [InscriptionPelerinageService.php](file:///c:/xampp/htdocs/catheo/app/Services/Organisation/InscriptionPelerinageService.php#L173-L177) pour pré-remplir les inscriptions aux pèlerinages à partir des catéchumènes de la section correspondante.

---

## PARTIE 8 — VÉRIFICATION DES LOGOS

### 8.1 Organisation (table `organisations`)
- **Colonnes en base** :
  - `logo_path` (`string(255)`, nullable) : **EXISTE**.
  - `logo` : **NON**.
  - `favicon` : **NON**.
  - `image` : **NON**.
- **Accesseur URL dans le modèle** :
  - Le modèle `Organisation.php` n'a actuellement aucun accesseur `logo_url` ou `logo_path_url`.
  - La méthode de mise à jour [OrganisationProfileController@update](file:///c:/xampp/htdocs/catheo/app/Http/Controllers/Api/V1/Organisation/OrganisationProfileController.php#L36-L45) n'inclut pas de règle d'upload de fichier pour `logo_path`.

### 8.2 Paroisse (table `paroisse_configurations`)
- **Colonnes en base** :
  - `logo_paroisse` (`string(255)`, nullable) : **EXISTE**.
  - `logo_catechese` (`string(255)`, nullable) : **EXISTE**.
  - `logo_path` (`string(255)`, nullable) : **EXISTE** (historique).
- **Accesseurs URL dans le modèle [CatecheseConfiguration.php](file:///c:/xampp/htdocs/catheo/app/Models/CatecheseConfiguration.php#L61-L95)** :
  - `getLogoParoisseUrlAttribute()` : pointe vers `storage/catechese/logos/paroisse/...`
  - `getLogoCatecheseUrlAttribute()` : pointe vers `storage/catechese/logos/catechese/...`
  - `getLogoUrlAttribute()` : alias vers `logo_paroisse_url`.

---

## PARTIE 9 — ANALYSE D'ALIGNEMENT ARCHITECTURAL

### Workflow souhaité par l'utilisateur :
```
Paroisse
  ↓
Détail Paroisse
  ↓
Bloc : Organisations rattachées
  [ Tableau : Produit | Statut | Actions (Détail) ]
  (Ex : OPPE - Actif, OPPJ - Actif, OPPA - Suspendu)
  ↓
Bouton : "Ajouter une organisation"
  Multi-select : [ OPPE, OPPJ, OPPA ]
  ↓
Création automatique des organisations
  ↓
Menu Organisations
  Affiche la liste avec indication "Paroisse : Saint Michel"
  Accès à la gestion : Logo, Responsable, Utilisateurs, Activités, Pèlerinages, Caisse.
```

### Le backend actuel permet-il cette architecture sans modification majeure ?
**OUI**, le modèle relationnel et la segmentation fonctionnelle existants sont **parfaitement alignés** avec cette vision :
- Chaque paroisse est bien le parent naturel des organisations OPPE, OPPJ, OPPA.
- Les modules métiers d'organisation (`membres`, `activites`, `campagnes_pelerinage`, `operations`, `users`) sont déjà isolés et opérationnels.
- Le provisionnement du premier responsable existe déjà (`POST /api/v1/super-admin/organisations/{id}/responsable`).

### Quelles adaptations techniques seront nécessaires pour l'étape F25 ?
Pour rendre ce workflow 100% exécutable depuis le frontend Super Admin sans casser l'existant :
1. **Création d'organisations en Super Admin** :
   - Implémenter l'endpoint `POST /api/v1/super-admin/organisations` (prenant `paroisse_id`, `produit_id` ou `type_organisation`, `nom`, etc.) avec création unitaire ou création multiple (batch via multi-select).
2. **Bloc organisations dans le détail paroisse** :
   - Aujourd'hui, [SuperAdminParoisseResource.php](file:///c:/xampp/htdocs/catheo/app/Http/Resources/Api/V1/SuperAdmin/SuperAdminParoisseResource.php) ne renvoie pas les organisations rattachées (il ne charge que `abonnements`).
   - Deux approches possibles sans casser le backend :
     - *Option A (Recommandée)* : Charger `with('organisations.produit')` dans `SuperAdminParoisseController@show` et exposer `organisations` dans `SuperAdminParoisseResource`.
     - *Option B (Sans toucher au contrôleur Paroisse)* : Le frontend appelle l'endpoint déjà existant `GET /api/v1/super-admin/organisations?paroisse_id={id}` qui renvoie exactement les organisations de cette paroisse.
3. **Modification / Statut organisation en Super Admin** :
   - Ajouter `PUT /api/v1/super-admin/organisations/{organisation}` et `PATCH /api/v1/super-admin/organisations/{organisation}/statut`.
4. **Clarification sur les abonnements** :
   - Si les abonnements doivent être portés par l'organisation plutôt que par la paroisse, ou si une organisation indépendante doit avoir son propre abonnement, une migration d'évolution sera requise sur `abonnements` (`organisation_id` nullable, `paroisse_configuration_id` nullable).
   - Si les abonnements restent au niveau de la paroisse (la paroisse souscrit aux formules des produits CATHEO, OPPE, OPPJ, OPPA), **aucune migration n'est nécessaire sur les abonnements**.

---

## PARTIE 10 — TABLEAU DE SYNTHÈSE FINAL

| Élément Métier / Technique | Existe déjà | Adaptation nécessaire pour F25 |
| :--- | :---: | :--- |
| **Plusieurs organisations par paroisse** | ✅ **OUI** | **Aucune adaptation BDD/Modèle**. Déjà modélisé, géré et protégé contre les doublons du même type par clé unique. |
| **Organisation indépendante (sans paroisse)** | ❌ **NON** | **Adaptation BDD majeure requise** si souhaité : rendre `paroisse_configuration_id` nullable dans `organisations`, adapter `SecurityContextService` et `users.paroisse_configuration_id`. |
| **Liaison produit ↔ organisation** | ✅ **OUI** | **Aucune adaptation**. La clé `produit_id` existe dans `organisations`, liée à `produits(id)`, et les relations Eloquent `produit()` / `organisations()` sont actives. |
| **Abonnement organisation** | ❌ **NON** *(Uniquement paroisse)* | Dans le backend actuel, les abonnements sont strictement rattachés à `paroisse_configuration_id`. Pour un abonnement direct par organisation, il faudrait ajouter `organisation_id` nullable dans `abonnements` et rendre `paroisse_configuration_id` nullable. |
| **Bloc organisations dans détail paroisse** | ❌ **NON (direct)**<br>*(Mais contournable)* | Dans `SuperAdminParoisseResource`, la clé `organisations` n'est pas exposée. Adaptation : soit ajouter `organisations` dans le détail paroisse, soit consommer l'endpoint existant `GET /api/v1/super-admin/organisations?paroisse_id={id}`. |
| **Logos organisation** | ⚠️ **PARTIEL** | La colonne `logo_path` existe en BDD dans `organisations`, mais il manque : un accesseur `logo_url`, la gestion du téléchargement de fichier (upload) dans l'API, et les éventuelles colonnes `favicon`/`image` si demandées. |
| **Création d'organisations via Super Admin** | ❌ **NON** | Endpoint manquant : `POST /api/v1/super-admin/organisations` à créer pour permettre l'ajout unitaire ou batch (multi-select). |
| **Création premier responsable d'organisation** | ✅ **OUI** | Endpoint déjà disponible et opérationnel : `POST /api/v1/super-admin/organisations/{org}/responsable`. |
| **Filtrage des organisations par paroisse** | ✅ **OUI** | Endpoint existant : `GET /api/v1/super-admin/organisations?paroisse_id={id}`. |
| **Passerelle Catheo Population (OPPE, OPPJ, OPPA)** | ✅ **OUI** | Service et endpoint opérationnels (`GET /api/v1/organisation/catheo/population`) avec filtrage strict par codes canoniques (`SEC-ENFANTS-PRI`, `SEC-ENFANTS-COL`, `SEC-JEUNES`, `SEC-ADULTES`). |

---

> **Conclusion de l'audit** :  
> Le backend est sain, robuste et cohérent avec la structure hiérarchique **Paroisse ➔ Organisations (OPPE, OPPJ, OPPA) ➔ Produits**.  
> Aucun fichier n'a été altéré. Ce rapport technique constitue la base d'arbitrage définitive pour le cadrage de l'étape F25.
