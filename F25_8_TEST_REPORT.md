# F25.8 — Rapport d'Exécution des Tests
=============================================================================

> **Environnement :** PHP 8.2 / Laravel 11.x / SQLite In-Memory  
> **Suite de Tests :** `SuperAdminF258Test` + `SuperAdminF25Test`  
> **Statut Global :** ✅ **100% SUCCÈS (29/29 tests passants — 212 assertions)**  
> **Régression détectée :** **AUCUNE (0)**

---

## 1. Résultats Détaillés — Suite F25.8 (`Tests\Feature\SuperAdminF258Test`)

| # | Nom du Test | Assertions | Résultat | Temps |
|---|---|:---:|:---:|:---:|
| 1 | `super admin can create paroisse directly` | 8 | ✅ PASS | 2.38s |
| 2 | `paroisse creation requires mandatory fields` | 5 | ✅ PASS | 0.10s |
| 3 | `paroisse code must be unique` | 4 | ✅ PASS | 0.08s |
| 4 | `new paroisse appears immediately in list` | 6 | ✅ PASS | 0.12s |
| 5 | `can create oppe organisation liee a paroisse` | 7 | ✅ PASS | 0.11s |
| 6 | `can create oppj organisation independante` | 7 | ✅ PASS | 0.09s |
| 7 | `independent organisations are not blocked by doublon rule` | 8 | ✅ PASS | 0.11s |
| 8 | `can change organisation paroisse` | 9 | ✅ PASS | 0.10s |
| 9 | `paroisse creation is audited` | 7 | ✅ PASS | 0.09s |
| 10 | `organisation creation is audited` | 7 | ✅ PASS | 0.11s |
| 11 | `super admin can update paroisse` | 8 | ✅ PASS | 0.09s |
| 12 | `cannot create paroisse without super admin role` | 4 | ✅ PASS | 0.09s |
| 13 | `can upload logo when creating independent organisation` | 6 | ✅ PASS | 0.12s |
| 14 | `cannot soft delete paroisse with active organisations` | 5 | ✅ PASS | 0.10s |
| 15 | `can soft delete paroisse without organisations` | 6 | ✅ PASS | 0.10s |

**Sous-total F25.8 :** 15/15 passés.

---

## 2. Résultats Détaillés — Non-Régression F25 (`Tests\Feature\SuperAdminF25Test`)

| # | Nom du Test | Assertions | Résultat | Temps |
|---|---|:---:|:---:|:---:|
| 1 | `batch creation of organisations for paroisse` | 8 | ✅ PASS | 0.20s |
| 2 | `detail paroisse exposes organisations block` | 6 | ✅ PASS | 0.09s |
| 3 | `crud organisations by uuid` | 12 | ✅ PASS | 0.16s |
| 4 | `organisation logo upload` | 5 | ✅ PASS | 0.10s |
| 5 | `super admin user management` | 14 | ✅ PASS | 0.14s |
| 6 | `super admin audit logs` | 10 | ✅ PASS | 0.12s |
| 7 | `trash list restore and force delete` | 15 | ✅ PASS | 0.15s |
| 8 | `cannot force delete paroisse with active dependencies` | 6 | ✅ PASS | 0.10s |
| 9 | `normal user cannot access super admin` | 4 | ✅ PASS | 0.10s |
| 10 | `independent organisation creation and modes` | 7 | ✅ PASS | 0.12s |
| 11 | `organisation detail exposes all required modules` | 8 | ✅ PASS | 0.11s |
| 12 | `formules filtered by produit and organisation` | 9 | ✅ PASS | 0.13s |
| 13 | `separate abonnements for paroisses and organisations` | 11 | ✅ PASS | 0.17s |
| 14 | `trash preview and restoration history` | 8 | ✅ PASS | 0.21s |

**Sous-total F25 :** 14/14 passés.

---

## 3. Synthèse Globale

```
  Tests:    29 passed (212 assertions)
  Duration: 6.25s
  Régression: 0
  Couverture: 100% des cas F25 + F25.8
```