# Feature Specification: Ρυθμίσεις Συνεργείου (`garage-settings`)

## 1. Context & Business Rationale (Σκοπός & Αιτιολόγηση)

Κάθε συνεργείο (tenant) στην πλατφόρμα χρειάζεται προσωποποιημένες επιχειρησιακές, λειτουργικές, οπτικές παραμέτρους και δικαιώματα ασφαλείας.
Μέχρι σήμερα, το [GarageProfileController.php](file:///home/clickdrive/Desktop/api/backendGarage/src/Controller/Api/Garage/GarageProfileController.php) διαχειρίζεται μόνο βασικά στοιχεία ταυτότητας (`name`, `email`, `phone`, `vatNumber`, `address`).

Με τη δυνατότητα **`garage-settings`**:
1. **Αυτόνομη Παραμετροποίηση ανά Συνεργείο:**
   - **`reminders` (Υπενθυμίσεις & Προθεσμίες):**
     - `kteoDaysBefore` *(default: 30)*: Ημέρες προειδοποίησης πριν τη λήξη ΚΤΕΟ.
     - `serviceMonthsInterval` *(default: 12)*: Προεπιλεγμένο διάστημα μηνών για επόμενο service (χρησιμοποιείται για αυτόματο υπολογισμό `nextServiceDate = today + 12 μήνες` κατά την ολοκλήρωση επισκευής).
     - `serviceKmInterval` *(default: 5000)*: Προεπιλεγμένα χιλιόμετρα για επόμενο service (χρησιμοποιείται για αυτόματο υπολογισμό `nextServiceMileage = currentKm + 5000`).
     - `autoRemindersDefault` *(default: true)*: Προεπιλεγμένη κατάσταση του checkbox «Αποδοχή υπενθυμίσεων» (`allowReminders`) κατά την καταχώρηση νέου οχήματος.
   - **`permissions` (Δικαιώματα & Guardrails):**
     - `mecCanTweakPrice` *(default: false)*: Καθορίζει αν ο μηχανικός (`ROLE_MECHANIC`) έχει δικαίωμα ορισμού ή τροποποίησης τιμής (`price`) στις εντολές εργασίας.
       - Όταν είναι `false`: Οποιαδήποτε απόπειρα τροποποίησης του πεδίου `price` από μηχανικό στο `PATCH /api/garage/work-orders/{id}` απορρίπτεται με **HTTP 403 Forbidden** (`MECHANIC_PRICE_TWEAK_FORBIDDEN`).
       - Όταν είναι `true`: Ο μηχανικός μπορεί να ενημερώσει την τιμή της επισκευής.
       - Ο διαχειριστής (`ROLE_GARAGE_ADMIN`) διατηρεί πάντοτε πλήρη δικαιώματα ορισμού και τροποποίησης τιμής.
   - **`presets` (Προεπιλογές Εργασιών & Τιμών):**
     - `quickWorkChips` *(default: `["Service", "Λάδια/Φίλτρο", "Τακάκια", "Αλυσίδα/Γρανάζια", "Μπαταρία", "Μπουζί", "Λάστιχα", "Έλεγχος"]`)*: Δυναμικά κουμπιά συχνών εργασιών για το modal επισκευής.
     - `quickPricePresets` *(default: `[20, 50, 80, 120]`)*: Γρήγορες επιλογές ποσών.
   - **`messaging` (Στοιχεία Μηνυμάτων):**
     - `smsSenderName` *(default: `"WorkshopHub"`)*: Όνομα αποστολέα που εμφανίζεται στα μηνύματα SMS/Viber προς τον πελάτη.
   - **`display` (Εμφάνιση):**
     - `darkMode` *(default: true)*: Προεπιλεγμένη κατάσταση σκοτεινής λειτουργίας του συνεργείου.

2. **Αρχιτεκτονική Δεδομένων:**
   - Χρήση **PostgreSQL `jsonb`** στο entity [`Garage`](file:///home/clickdrive/Desktop/api/backendGarage/src/Entity/Garage.php) (`settings: array`).
   - Deep merge με σταθερές προεπιλογές (`DEFAULT_SETTINGS`) μέσω `array_replace_recursive` στη μέθοδο `Garage::getSettings()`. Εξασφαλίζει ότι νέα κλειδιά ρυθμίσεων λειτουργούν άμεσα για όλα τα υπάρχοντα και νέα συνεργεία χωρίς schema break.
   - Δημιουργία και εκτέλεση Doctrine migration για την προσθήκη της στήλης `settings jsonb NOT NULL DEFAULT '{}'`.

3. **Ασφάλεια & Multi-Tenant Απομόνωση:**
   - Ανάγνωση (`GET /api/garage/settings`): Προσβάσιμη σε `ROLE_MECHANIC` και `ROLE_GARAGE_ADMIN`.
   - Τροποποίηση (`PATCH /api/garage/settings`): Αυστηρά περιορισμένη σε `ROLE_GARAGE_ADMIN`.
   - Multi-tenant Scoping: Πάντοτε απομονωμένο στο συνεργείο του συνδεδεμένου χρήστη (`$user->getGarage()`).

---

## 2. Μοντέλο Δεδομένων & Προεπιλογές (Data Model & Defaults)

### 2.1 Entity [`Garage.php`](file:///home/clickdrive/Desktop/api/backendGarage/src/Entity/Garage.php)
```php
#[ORM\Column(type: Types::JSON, options: ['jsonb' => true], nullable: false)]
private array $settings = [];

public const DEFAULT_SETTINGS = [
    'reminders' => [
        'kteoDaysBefore' => 30,
        'serviceMonthsInterval' => 12,
        'serviceKmInterval' => 5000,
        'autoRemindersDefault' => true,
    ],
    'permissions' => [
        'mecCanTweakPrice' => false,
    ],
    'presets' => [
        'quickWorkChips' => [
            'Service',
            'Λάδια/Φίλτρο',
            'Τακάκια',
            'Αλυσίδα/Γρανάζια',
            'Μπαταρία',
            'Μπουζί',
            'Λάστιχα',
            'Έλεγχος',
        ],
        'quickPricePresets' => [20, 50, 80, 120],
    ],
    'messaging' => [
        'smsSenderName' => 'WorkshopHub',
    ],
    'display' => [
        'darkMode' => true,
    ],
];

public function getSettings(): array
{
    return array_replace_recursive(self::DEFAULT_SETTINGS, $this->settings);
}

public function setSettings(array $settings): static
{
    $this->settings = $settings;
    return $this;
}
```

---

## 3. Endpoints & API Contract

### 3.1 Ανάγνωση Ρυθμίσεων (`GET /api/garage/settings`)
* **Endpoint:** `GET /api/garage/settings`
* **Πρόσβαση:** `#[IsGranted('ROLE_MECHANIC')]`
* **Response 200 OK:**
```json
{
  "reminders": {
    "kteoDaysBefore": 30,
    "serviceMonthsInterval": 12,
    "serviceKmInterval": 5000,
    "autoRemindersDefault": true
  },
  "permissions": {
    "mecCanTweakPrice": false
  },
  "presets": {
    "quickWorkChips": [
      "Service",
      "Λάδια/Φίλτρο",
      "Τακάκια",
      "Αλυσίδα/Γρανάζια",
      "Μπαταρία",
      "Μπουζί",
      "Λάστιχα",
      "Έλεγχος"
    ],
    "quickPricePresets": [20, 50, 80, 120]
  },
  "messaging": {
    "smsSenderName": "WorkshopHub"
  },
  "display": {
    "darkMode": true
  }
}
```

### 3.2 Ενημέρωση Ρυθμίσεων (`PATCH /api/garage/settings`)
* **Endpoint:** `PATCH /api/garage/settings`
* **Πρόσβαση:** `#[IsGranted('ROLE_GARAGE_ADMIN')]`
* **Request Payload (υποστηρίζει partial deep updates):**
```json
{
  "permissions": {
    "mecCanTweakPrice": true
  },
  "messaging": {
    "smsSenderName": "Sarris Moto"
  },
  "display": {
    "darkMode": false
  }
}
```
* **Response 200 OK:** Επιστρέφει το πλήρες συγχωνευμένο αντικείμενο ρυθμίσεων.
* **Response Codes:**
  - `200 OK`: Επιτυχής ενημέρωση.
  - `401 Unauthorized`: Μη αυθεντικοποιημένος χρήστης.
  - `403 Forbidden`: Κλήση από μηχανικό (`ROLE_MECHANIC`).
  - `422 Unprocessable Content`: Μη έγκυρες τιμές.

### 3.3 Εμπλουτισμός Προφίλ (`GET /api/garage`)
Στην απάντηση του `GET /api/garage` (μέθοδος `getProfile` στο [GarageProfileController.php](file:///home/clickdrive/Desktop/api/backendGarage/src/Controller/Api/Garage/GarageProfileController.php)), περιλαμβάνεται πλέον και το κλειδί:
`'settings' => $garage->getSettings()`

---

## 4. Επίδραση Επιχειρησιακής Λογικής (`mecCanTweakPrice`)

Στη μέθοδο `update` του [WorkOrderController.php](file:///home/clickdrive/Desktop/api/backendGarage/src/Controller/Api/Garage/WorkOrderController.php):
- Εάν ένας χρήστης χωρίς ρόλο `ROLE_GARAGE_ADMIN` στείλει `price !== null`:
  - Ελέγχεται το `$garage->getSettings()['permissions']['mecCanTweakPrice']`.
  - Εάν είναι `false`: Επιστρέφεται **HTTP 403 Forbidden** με κωδικό `MECHANIC_PRICE_TWEAK_FORBIDDEN` και επεξηγηματικό μήνυμα: «Δεν επιτρέπεται η τροποποίηση τιμής από μηχανικό βάσει των ρυθμίσεων του συνεργείου.».
  - Εάν είναι `true`: Η ενημέρωση τιμής επιτρέπεται κανονικά.

---

## 5. DTOs & Validation Rules

Δημιουργία DTOs υπό το namespace `App\DTO\Garage\Settings`:
- `UpdateGarageSettingsRequest`:
  - `?RemindersSettingsRequest $reminders`
  - `?PermissionsSettingsRequest $permissions`
  - `?PresetsSettingsRequest $presets`
  - `?MessagingSettingsRequest $messaging`
  - `?DisplaySettingsRequest $display`

Κανόνες επικύρωσης:
- `kteoDaysBefore`: ακέραιος $\ge 1$ και $\le 365$.
- `serviceMonthsInterval`: ακέραιος $\ge 1$ και $\le 60$.
- `serviceKmInterval`: ακέραιος $\ge 500$ και $\le 100000$.
- `autoRemindersDefault`: boolean.
- `mecCanTweakPrice`: boolean.
- `quickWorkChips`: array από μη κενά strings, μέγιστο μήκος string 50 χαρακτήρες, μέγιστος αριθμός chips 20.
- `quickPricePresets`: array από θετικούς αριθμούς, μέγιστος αριθμός presets 10.
- `smsSenderName`: string, μήκος 1 έως 50 χαρακτήρες.
- `darkMode`: boolean.

---

## 6. Προδιαγραφές Ελέγχων (Testing Requirements)

Δημιουργία και ενημέρωση των λειτουργικών δοκιμών (`WebTestCase`):

1. **`tests/Garage/GarageSettingsTest.php`**:
   - `testFetchDefaultSettings`: Επαληθεύει ότι νέο συνεργείο λαμβάνει όλες τις προεπιλογές (reminders, permissions, presets, messaging, display).
   - `testPartialPatchSettings`: Επαληθεύει ότι μερική ενημέρωση (π.χ. μόνο `darkMode` ή μόνο `mecCanTweakPrice`) ενημερώνει τα συγκεκριμένα πεδία χωρίς να διαγράφει τα υπόλοιπα.
   - `testMechanicCanReadSettings`: Επαληθεύει ότι χρήστης με `ROLE_MECHANIC` διαβάζει επιτυχώς τις ρυθμίσεις (`GET /api/garage/settings` -> 200).
   - `testMechanicCannotUpdateSettings`: Επαληθεύει ότι χρήστης με `ROLE_MECHANIC` απορρίπτεται κατά την προσπάθεια τροποποίησης (`PATCH /api/garage/settings` -> 403 Forbidden).
   - `testValidationRejectsInvalidData`: Επαληθεύει ότι άκυρες τιμές (π.χ. αρνητικά νούμερα, μη boolean τιμές) επιστρέφουν 422 Unprocessable Content.
   - `testCrossTenantIsolation`: Επαληθεύει ότι τροποποίηση ρυθμίσεων από το Συνεργείο Α δεν επηρεάζει τις ρυθμίσεις του Συνεργείου Β.
   - `testProfileEndpointIncludesSettings`: Επαληθεύει ότι το `GET /api/garage` επιστρέφει το αντικείμενο `settings`.
   - `testUnauthenticatedRejected`: Επαληθεύει επιστροφή 401 Unauthorized χωρίς JWT.

2. **`tests/Garage/WorkOrderManagementTest.php`**:
   - Επαλήθευση του guardrail `mecCanTweakPrice`:
     - Όταν `mecCanTweakPrice === false`, απόπειρα μηχανικού να αλλάξει τιμή επιστρέφει **HTTP 403 Forbidden** (`MECHANIC_PRICE_TWEAK_FORBIDDEN`).
     - Όταν `mecCanTweakPrice === true`, ο μηχανικός αλλάζει επιτυχώς τιμή (**HTTP 200 OK**).
     - Ο διαχειριστής αλλάζει πάντοτε τιμή ανεξάρτητα από τη ρύθμιση.
