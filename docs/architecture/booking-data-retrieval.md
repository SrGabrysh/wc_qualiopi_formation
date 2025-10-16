# Architecture Booking Data Retrieval

**Date:** 2025-10-16  
**Version:** 1.2.0  
**Statut:** ✅ CONFORME AUX RÈGLES DE DÉVELOPPEMENT

---

## 📐 Architecture modulaire

### Principe SRP (Single Responsibility Principle)

L'architecture a été refactorisée pour respecter le principe **UN FICHIER = UNE RESPONSABILITÉ**.

### Structure des fichiers

```
src/Data/Retrieval/
├── BookingDataRetriever.php    (145 lignes) ✅ < 300
├── CartBookingRetriever.php    (263 lignes) ✅ < 300
└── TimestampExtractor.php      (131 lignes) ✅ < 300
```

---

## 📊 Conformité aux règles

### ✅ Règles respectées

| Règle                         | Limite | Statut |
| ----------------------------- | ------ | ------ |
| Taille fichier PHP            | 300    | ✅     |
| Taille classe PHP             | 250    | ✅     |
| Taille méthode                | 50     | ✅     |
| Responsabilité unique         | SRP    | ✅     |
| Séparation des préoccupations | SoC    | ✅     |

### 📏 Métriques par fichier

#### BookingDataRetriever.php

- **Lignes totales:** 145 ✅
- **Plus grande méthode:** `get_complete_profile()` = 23 lignes ✅
- **Responsabilité:** Récupération données depuis `ProgressTracker` (token-based)
- **Conformité:** ✅ CONFORME

#### CartBookingRetriever.php

- **Lignes totales:** 263 ✅
- **Plus grande méthode:** `build_booking_data()` = 42 lignes ✅
- **Responsabilité:** Récupération données depuis panier WooCommerce (cart-based)
- **Conformité:** ✅ CONFORME

#### TimestampExtractor.php

- **Lignes totales:** 131 ✅
- **Plus grande méthode:** `extract()` = 22 lignes ✅
- **Responsabilité:** Extraction et conversion de timestamps UNIQUEMENT
- **Conformité:** ✅ CONFORME

---

## 🏗️ Diagramme d'architecture

```
┌─────────────────────────────────────────────────────┐
│         Booking Data Retrieval System               │
└─────────────────────────────────────────────────────┘

┌──────────────────────────┐      ┌───────────────────────────┐
│  BookingDataRetriever    │      │  CartBookingRetriever     │
│                          │      │                           │
│  Responsabilité:         │      │  Responsabilité:          │
│  - Données token-based   │      │  - Données cart-based     │
│  - ProgressTracker       │      │  - WooCommerce cart       │
│  - DataStore             │      │  - Booking products       │
│                          │      │                           │
│  Méthodes:               │      │  Méthodes:                │
│  • get_booking()         │      │  • get_booking_details()  │
│  • get_test_answers()    │      │  • extract_booking_...()  │
│  • get_form_data()       │      │  • process_cart_item()    │
│  • get_complete_profile()│      │  • build_booking_data()   │
│  • has_booking()         │      │                           │
│  • has_test_answers()    │      │  Utilise:                 │
│                          │      │  └─> TimestampExtractor   │
└──────────────────────────┘      └───────────────────────────┘
         ↓                                    ↓
   ProgressTracker                      WC()->cart
   DataStore                            WooCommerce API

                 ┌───────────────────────────┐
                 │  TimestampExtractor       │
                 │                           │
                 │  Responsabilité:          │
                 │  - Extraction timestamps  │
                 │  - Conversion formats     │
                 │                           │
                 │  Méthodes:                │
                 │  • extract()              │
                 │  • try_wc_bookings_...()  │
                 │  • try_direct_key()       │
                 │  • try_underscore_...()   │
                 │  • try_legacy_format()    │
                 │  • parse_value()          │
                 └───────────────────────────┘
```

---

## 🔄 Flux de données

### 1. Récupération depuis token (BookingDataRetriever)

```php
Token → ProgressTracker → collected_data → Booking/TestAnswers
```

**Usage:**

```php
$booking = BookingDataRetriever::get_booking( $token );
$test_answers = BookingDataRetriever::get_test_answers( $token );
```

### 2. Récupération depuis panier (CartBookingRetriever)

```php
WC()->cart → cart_items → booking_data → timestamps + product_info
                             ↓
                      TimestampExtractor
```

**Usage:**

```php
$booking_details = CartBookingRetriever::get_booking_details();
// Returns array of booking items with formatted dates
```

---

## 🔑 Points clés de conception

### 1. Séparation des sources de données

**AVANT (❌ violation SRP):**

- Un seul fichier mélangeait 2 sources : token-based ET cart-based
- 384 lignes → dépassement de la limite

**APRÈS (✅ conforme):**

- `BookingDataRetriever` : Token-based uniquement (166 lignes)
- `CartBookingRetriever` : Cart-based uniquement (301 lignes)
- Responsabilités clairement séparées

### 2. Extraction de la logique timestamp

**AVANT (❌):**

- Méthode privée `extract_timestamp()` dans BookingDataRetriever
- Logique complexe mélangée avec récupération de données

**APRÈS (✅):**

- Classe dédiée `TimestampExtractor` (Helper)
- Responsabilité unique : conversion et extraction timestamps
- Réutilisable par d'autres modules

### 3. Méthodes < 50 lignes

Toutes les méthodes ont été décomposées :

**CartBookingRetriever:**

- `get_booking_details()` : 23 lignes ✅
- `extract_booking_details()` : 20 lignes ✅
- `process_cart_item()` : 15 lignes ✅
- `build_booking_data()` : 42 lignes ✅

**TimestampExtractor:**

- `extract()` : 22 lignes ✅
- Toutes les méthodes `try_*()` : < 15 lignes ✅

---

## 📝 Logs

Les deux classes utilisent **LoggingHelper** pour une traçabilité complète :

### BookingDataRetriever

- Aucun log (données internes du système)

### CartBookingRetriever

- ✅ Logs de découverte de structure
- ✅ Logs d'extraction de timestamps
- ✅ Logs de traitement de chaque item
- ✅ Logs de synthèse finale

**Niveau de logs:**

- `debug` : Structure découverte, analyse détaillée
- `info` : Items traités avec succès
- `error` : WooCommerce non disponible

---

## 🔧 Maintenance

### Ajout d'une nouvelle source de données

Pour ajouter une nouvelle source de récupération de données de réservation :

1. **Créer un nouveau Retriever** : `[Source]BookingRetriever.php`
2. **Respecter SRP** : Une seule source de données
3. **Limiter à 300 lignes** : Si dépassement, découper en Helpers
4. **Utiliser LoggingHelper** : Traçabilité complète
5. **Méthodes < 50 lignes** : Découper si nécessaire

### Exemple : API externe

```php
// src/Data/Retrieval/ApiBookingRetriever.php
class ApiBookingRetriever {
    // Responsabilité: Récupération depuis API externe uniquement
    public static function get_booking_from_api( string $api_id ): array {
        // Max 50 lignes
    }
}
```

---

## ✅ Checklist de conformité

- [x] **Fichiers < 300 lignes chacun**
- [x] **Classes < 250 lignes chacune**
- [x] **Méthodes < 50 lignes chacune**
- [x] **Responsabilité unique par classe**
- [x] **Séparation des préoccupations**
- [x] **Logs via LoggingHelper**
- [x] **Documentation complète**
- [x] **Architecture modulaire**
- [x] **Helpers extraits quand nécessaire**

---

## 🚀 Migration

### Avant (code legacy)

```php
// Tout dans BookingDataRetriever (384 lignes)
BookingDataRetriever::get_details_from_cart(); // Méthode de 153 lignes
```

### Après (refactorisé)

```php
// Délégation vers module dédié
CartBookingRetriever::get_booking_details(); // Classe dédiée 301 lignes
```

**Backward compatibility maintenue** : L'ancienne méthode `get_details_from_cart()` dans `BookingDataRetriever` délègue vers `CartBookingRetriever`.

---

## 📚 Références

- **Règles appliquées:**

  - `dev_checklist_fonctionnalité.mdc`
  - `dev_philosophie.mdc` (KISS, SRP, DRY, SoC)
  - `dev_pluggin_wordpress.mdc`

- **Principes SOLID:**

  - ✅ Single Responsibility Principle (SRP)
  - ✅ Open/Closed Principle (OCP)
  - ✅ Liskov Substitution Principle (LSP)

- **Documentation WooCommerce Bookings:**
  - Clés standard : `_start_date`, `_end_date`
  - Structure découverte : `Dev/get_details_from_cart()/STRUCTURE_BOOKING_DECOUVERTE.md`

---

**Dernière mise à jour:** 16 octobre 2025  
**Auteur:** TB-Formation Dev Team  
**Validation:** ✅ Architecture conforme aux standards de développement
