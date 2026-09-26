# F25.8 — RAPPORT D'AUDIT PRÉALABLE (PRE-AUDIT)
=============================================================================

> **Date :** 24 Septembre 2026
> **Statut des tests F25 avant modifications :** ✅ 14/14 tests passants (164 assertions)

## 1. Routes Super Admin Paroisses

| Méthode | Route | Statut |
|:---:|:---|:---:|
| GET | /api/v1/super-admin/paroisses | ✅ Existant |
| GET | /api/v1/super-admin/paroisses/{id} | ✅ Existant |
| POST | /api/v1/super-admin/paroisses | ❌ MANQUANT — À créer |
| PUT | /api/v1/super-admin/paroisses/{id} | ❌ MANQUANT — À créer |
| DELETE | /api/v1/super-admin/paroisses/{id} | ❌ MANQUANT — À créer |

## 2. Modèle CatecheseConfiguration (table: paroisse_configurations)

Traits actifs: HasUuid ✅, SoftDeletes ✅, Auditable ✅
Champs disponibles: id, uuid, nom_paroisse, code_paroisse, prefixe_matricule, prefixe_recu,
diocese, doyenne, ville, commune, telephone, email, site_web, adresse,
logo_paroisse, logo_catechese, logo_path, cure_nom, coordination_nom, statut,
created_at, updated_at, created_by, updated_by, deleted_by, deleted_at

## 3. Modèle Organisation (table: organisations)

Traits actifs: HasUuid ✅, SoftDeletes ✅, Auditable ✅
Produits en base: CATHEO (id=1), OPPE (id=2), OPPJ (id=3), OPPA (id=4)

Bug identifié dans store(): la règle anti-doublon OPPE-par-paroisse
bloque aussi les organisations indépendantes — à corriger.

## 4. Form Requests SuperAdmin Existantes (à ne pas modifier)

StoreAbonnementRequest.php ✅
StoreFormuleRequest.php ✅
StorePaiementAbonnementRequest.php ✅
StoreProduitRequest.php ✅
StoreResponsableOrganisationRequest.php ✅
UpdateFormuleRequest.php ✅
UpdateProduitRequest.php ✅

À CRÉER: StoreParoisseRequest.php, UpdateParoisseRequest.php, StoreOrganisationDirectRequest.php

## 5. RBAC

Middleware: auth:sanctum + super_admin sur tout le groupe super-admin.
Aucune permission granulaire nécessaire — cohérent avec F25.

## 6. ActionAuditService — Signature

ActionAuditService::log(
    action, module, description, entite,
    anciennesValeurs, nouvellesValeurs,
    paroisseId, organisationId, request
)

## 7. Baseline Tests F25 = 14/14 ✅ (164 assertions)

## 8. Plan F25.8

BACKEND:
  Phase B: StoreParoisseRequest + SuperAdminParoisseController@store
  Phase C: List enrichie (déjà via index, à vérifier)
  Phase D: StoreOrganisationDirectRequest + corriger store()
  Phase E: Modifier rattachement paroisse via update()
  Phase F: Corriger règle doublon (exclure indépendantes)
  Phase G: Audit déjà via ActionAuditService
  Phase I: Nouveaux tests dans SuperAdminF258Test.php

FRONTEND:
  Phase H: Modal paroisse + Modal organisation amélioré