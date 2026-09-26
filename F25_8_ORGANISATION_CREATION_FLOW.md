# F25.8 — Flux de Création et Gestion Autonome des Organisations
=============================================================================

> **Version :** F25.8  
> **Date :** 24 Septembre 2026  
> **Statut :** Opérationnel & Validé

---

## 1. Vue d'Ensemble du Workflow

Avant F25.8, la création d'organisations dépendait du rattachement obligatoire par lot depuis le menu Paroisses (`POST /api/v1/super-admin/organisations` avec tableau de produits).

Désormais, le Super Admin dispose de **deux modes** transparents et unifiés :
1. **Organisation Liée :** L'organisation (OPPE, OPPJ ou OPPA) est rattachée à une paroisse via `paroisse_id`.
2. **Organisation Indépendante :** L'organisation est créée de manière autonome (`independant = true` ou `paroisse_id = null`), sans paroisse de rattachement.

```
                  ┌──────────────────────────────────────────────┐
                  │ Super Admin — Menu Organisations             │
                  └──────────────────────┬───────────────────────┘
                                         │
                                         ▼
                          [ Bouton "Nouvelle organisation" ]
                                         │
                                         ▼
                     ┌───────────────────────────────────────┐
                     │ Modal de Création Multi-Blocs         │
                     │ - Type (OPPE, OPPJ, OPPA)             │
                     │ - Nom & Description                   │
                     │ - Paroisse (Liée OU Indépendante)     │
                     │ - Coordonnées & Responsable           │
                     │ - Upload Logo                         │
                     └───────────────────┬───────────────────┘
                                         │
                       ┌─────────────────┴─────────────────┐
                       ▼                                   ▼
        Mode Lié (paroisse_id fourni)        Mode Indépendant (Aucune)
        - Vérification unicité locale        - Règle doublon non bloquante
        - Rattachement automatique           - mode = "independant"
                       │                                   │
                       └─────────────────┬─────────────────┘
                                         ▼
                      POST /api/v1/super-admin/organisations
                                         │
                       ┌─────────────────┴─────────────────┐
                       ▼                                   ▼
              Création en Base (organisations)      Journalisation Audit
              + Provisioning Responsable (opt.)     (Module: Organisation)
```

---

## 2. Payload API Unifié (`POST /api/v1/super-admin/organisations`)

Le backend détecte automatiquement le mode à la présence des champs `type_organisation` et `nom` :

### Exemple : Organisation Liée
```json
{
  "type_organisation": "OPPE",
  "nom": "OPPE Saint Michel",
  "paroisse_id": "4c43ba4c-1e6a-464a-95b6-79ec9ad4eb68",
  "description": "Organisation pastorale des enfants",
  "telephone": "+2250102030405",
  "email": "oppe.stmichel@catheo.ci",
  "adresse": "Angré, Cocody",
  "responsable_nom": "Sœur Marie-Claire",
  "responsable_telephone": "+2250708091011",
  "responsable_email": "marieclaire@catheo.ci"
}
```

### Exemple : Organisation Indépendante
```json
{
  "type_organisation": "OPPJ",
  "nom": "OPPJ Communauté Saint Paul",
  "independant": true,
  "description": "Organisation pastorale autonome des jeunes",
  "telephone": "+2250700000000",
  "email": "oppj.stpaul@catheo.ci"
}
```

---

## 3. Modification du Rattachement (Phase E)

Le Super Admin peut modifier le rattachement d'une organisation à tout moment :
- **Passage d'indépendante à liée :** fournir `paroisse_id: "uuid_paroisse"`
- **Passage de liée à une autre paroisse :** fournir le nouvel `uuid_paroisse`
- **Passage de liée à indépendante :** fournir `paroisse_id: null` ou `independant: true`

Endpoint : `PUT /api/v1/super-admin/organisations/{uuid}` (ou `POST` avec `_method=PUT` en multipart).

### Journalisation d'Audit
La modification du rattachement enregistre les anciennes et nouvelles valeurs de `paroisse_configuration_id` pour une traçabilité totale.