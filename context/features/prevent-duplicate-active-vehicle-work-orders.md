# Feature Specification: Αποτροπή Διπλότυπων Ενεργών Εργασιών για το Ίδιο Όχημα (`prevent-duplicate-active-vehicle-work-orders`)

## 1. Context & Business Rationale (Σκοπός & Αιτιολόγηση)

Ένα όχημα (μηχανάκι/αυτοκίνητο) στον πραγματικό κόσμο μπορεί να βρίσκεται μόνο σε μία κατάσταση τη φορά μέσα στο συνεργείο.
Όταν ένα όχημα:
1. **Είναι ήδη στο συνεργείο** (`checked_in`, `in_progress` / `active`, `waiting_parts`, `waiting_customer`, `completed` πριν την παράδοση).
2. **Βρίσκεται προγραμματισμένο στην ουρά αναμονής** (`scheduled` / ραντεβού).

Είναι λογικά, λειτουργικά και επιχειρησιακά εσφαλμένο να εισάγεται εκ νέου ως «νέο όχημα / νέα εργασία».
Στην τρέχουσα υλοποίηση του backend (`POST /api/garage/work-orders`), επιτρέπεται η δημιουργία πολλαπλών ταυτόχρονων εργασιών για το ίδιο όχημα χωρίς έλεγχο, προκαλώντας:
- Διπλότυπες εγγραφές στο dashboard του μηχανικού.
- Σύγχυση σχετικά με την τρέχουσα κατάσταση, περιγραφή και χρέωση του οχήματος.
- Αποσυγχρονισμό χρονικών αποτυπωμάτων (`checkedInAt`, `completedAt`, `pickedUpAt`).

**Στόχος Fix**:
- Απαγόρευση δημιουργίας νέας εργασίας (`POST /api/garage/work-orders`) αν το όχημα έχει ήδη ενεργή ή προγραμματισμένη εργασία στο συνεργείο, επιστρέφοντας **HTTP 409 Conflict**.
- Εμπλουτισμός του endpoint έξυπνης αναζήτησης (`GET /api/garage/lookup`) με ένδειξη αν το όχημα έχει ήδη ενεργή εργασία (`hasActiveWorkOrder`, `activeWorkOrder`), ώστε το frontend να ενημερώνει άμεσα τον μηχανικό.
- Προσθήκη επιπέδου ασφαλείας στη βάση δεδομένων (PostgreSQL Partial Unique Index) για αποτροπή race conditions.

---

## 2. Λειτουργικές Προδιαγραφές (Functional Specifications)

### 2.1 Ορισμός Ενεργής / Ανοιχτής Εργασίας (Active Work Order Definition)
Μία εργασία (`WorkOrder`) θεωρείται **ενεργή** (δηλαδή δεσμεύει το όχημα στο συνεργείο ή στην ουρά) όταν πληροί τις παρακάτω συνθήκες:
- `workOrder.garage = :currentGarage`
- `workOrder.vehicle = :targetVehicle`
- `workOrder.pickedUpAt IS NULL`
- `workOrder.status NOT IN ('delivered', 'cancelled')`

Καταστάσεις που θεωρούνται ενεργές:
- `scheduled` (στην ουρά / ραντεβού)
- `checked_in` (παραλαβή / αναμονή για επισκευή)
- `in_progress` / `active` (σε εξέλιξη επισκευής)
- `waiting_parts` (αναμονή ανταλλακτικών)
- `waiting_customer` (αναμονή έγκρισης πελάτη)
- `completed` (ολοκληρώθηκε, αλλά δεν παραδόθηκε ακόμα στον πελάτη)

Καταστάσεις που **ΔΕΝ** είναι ενεργές (επιτρέπουν νέα εισαγωγή):
- `delivered` (το όχημα παραδόθηκε και αρχειοθετήθηκε, `pickedUpAt` συμπληρωμένο)
- `cancelled` (η εργασία ακυρώθηκε)

---

### 2.2 Έλεγχος & Απόκριση στο `WorkOrderController::create` (`POST /api/garage/work-orders`)

1. **Έλεγχος**:
   Πριν τη δημιουργία νέου `WorkOrder`, εκτελείται έλεγχος στο `WorkOrderRepository`:
   ```php
   $activeWorkOrder = $this->workOrderRepository->findActiveWorkOrderByVehicle($garage, $vehicle);
   ```
2. **Απόκριση σε περίπτωση διπλότυπης ενεργής εργασίας**:
   Αν βρεθεί ενεργή εργασία, η αίτηση απορρίπτεται άμεσα με **HTTP 409 Conflict**:
   ```json
   {
     "error": "Το όχημα βρίσκεται ήδη στο συνεργείο ή στην ουρά αναμονής με ενεργή εργασία.",
     "code": "VEHICLE_ALREADY_ACTIVE",
     "activeWorkOrder": {
       "id": "01920e10-1234-7890-abcd-1234567890ab",
       "status": "in_progress",
       "description": "Service & Αλλαγή λαδιών",
       "date": "2026-09-23",
       "scheduledTime": null,
       "checkedInAt": "2026-09-23T10:00:00+00:00"
     }
   }
   ```

---

### 2.3 Εμπλουτισμός Έξυπνης Αναζήτησης (`GET /api/garage/lookup`)

Στο `LookupController::formatVehicle()`, προστίθενται τα πεδία:
- `'hasActiveWorkOrder' => bool`
- `'activeWorkOrder' => array|null` (συνοπτικά στοιχεία ενεργής εργασίας: `id`, `status`, `description`, `date`)

Αυτό επιτρέπει στο Frontend UI (modal εισαγωγής νέου πελάτη/οχήματος) να αναγνωρίζει ακαριαία αν το επιλεγμένο όχημα είναι ήδη μέσα στο συνεργείο και να αποτρέπει τον μηχανικό από λάθος καταχώρηση.

---

### 2.4 Εγγύηση Ακεραιότητας Βάσης Δεδομένων (Database Constraint)

Δημιουργία PostgreSQL Partial Unique Index στον πίνακα `work_order`:
```sql
CREATE UNIQUE INDEX uniq_active_work_order_per_vehicle 
ON work_order (garage_id, vehicle_id) 
WHERE status NOT IN ('delivered', 'cancelled') AND picked_up_at IS NULL;
```
Αυτό διασφαλίζει ότι ακόμα και σε ταυτόχρονες αιτήσεις (concurrency / race conditions), η βάση δεδομένων δεν θα επιτρέψει ποτέ διπλή ενεργή εργασία για το ίδιο όχημα στον ίδιο ένοικο (tenant).

---

## 3. Αρχεία προς Τροποποίηση / Δημιουργία

1. **`src/Repository/WorkOrderRepository.php`**:
   - Προσθήκη μεθόδου `findActiveWorkOrderByVehicle(Garage $garage, Vehicle $vehicle): ?WorkOrder`.
   - Προσθήκη μεθόδου `hasActiveWorkOrder(Garage $garage, Vehicle $vehicle): bool`.
2. **`src/Controller/Api/Garage/WorkOrderController.php`**:
   - Ενσωμάτωση του ελέγχου στο `create()`.
   - Επιστροφή HTTP 409 Conflict με δομημένο σφάλμα `VEHICLE_ALREADY_ACTIVE`.
3. **`src/Controller/Api/Garage/LookupController.php`**:
   - Εμπλουτισμός του `formatVehicle()` με πληροφορία ενεργής εργασίας.
4. **`src/Entity/WorkOrder.php` & Migration**:
   - Προσθήκη partial unique constraint στο `WorkOrder`.
   - Εκτέλεση Doctrine migration.
5. **`tests/Garage/WorkOrderManagementTest.php` & `tests/Garage/LookupApiTest.php`**:
   - Προσθήκη λειτουργικών δοκιμών για HTTP 409 Conflict, επαναφορά μετά από `delivered`/`cancelled`, και lookup ενδείξεις.

---

## 4. Σχέδιο Επαλήθευσης & Δοκιμών (Testing Plan)

1. **Δοκιμή Απόρριψης (HTTP 409 Conflict)**:
   - Δημιουργία οχήματος και αρχικής εργασίας με status `checked_in`.
   - Αποστολή δεύτερης αίτησης `POST /api/garage/work-orders` για το ίδιο `vehicleId` -> επιβεβαίωση HTTP 409, `VEHICLE_ALREADY_ACTIVE`.
2. **Δοκιμή για Ουρά Αναμονής (`scheduled`)**:
   - Δημιουργία εργασίας με status `scheduled` (ραντεβού ουράς).
   - Αποστολή δεύτερης αίτησης για το ίδιο όχημα -> επιβεβαίωση HTTP 409.
3. **Δοκιμή Επιτυχίας μετά από Παράδοση (`delivered`)**:
   - Ολοκλήρωση και παράδοση της εργασίας (`status = delivered`, `pickedUpAt = now`).
   - Αποστολή νέας αίτησης για το ίδιο όχημα -> επιβεβαίωση HTTP 201 Created.
4. **Δοκιμή Επιτυχίας μετά από Ακύρωση (`cancelled`)**:
   - Ακύρωση υπάρχουσας εργασίας (`status = cancelled`).
   - Αποστολή νέας αίτησης για το ίδιο όχημα -> επιβεβαίωση HTTP 201 Created.
5. **Εκτέλεση Όλων των Tests**:
   - `docker compose exec -T php php bin/phpunit`.
