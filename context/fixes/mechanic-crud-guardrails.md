# Fix Specification: Έλεγχοι Ασφαλείας & CRUD Guardrails Μηχανικού (`mechanic-crud-guardrails`)

## 1. Context & Business Rationale (Σκοπός & Αιτιολόγηση)

Έπειτα από πλήρη έλεγχο των δικαιωμάτων πρόσβασης (Access Control & CRUD Audit) για τον ρόλο του Μηχανικού (`ROLE_MECHANIC`) σε όλους τους controllers του backend, εντοπίστηκαν 3 σοβαρές αδυναμίες ασφαλείας και επιχειρησιακής ακεραιότητας:

1. **Διαγραφή Οχήματος (`VehicleController::delete`):**
   Ο μηχανικός έχει δικαίωμα να διαγράψει οποιοδήποτε όχημα. Λόγω του κανόνα `ON DELETE CASCADE` στη σχέση `WorkOrder -> Vehicle` στη βάση δεδομένων, η διαγραφή ενός οχήματος σβήνει ακαριαία ολόκληρο το ιστορικό επισκευών και όλες τις ανοιχτές εντολές εργασίας. Αντίθετα, στο `CustomerController::delete`, η διαγραφή έχει πολύ σωστά περιοριστεί αποκλειστικά στον `ROLE_GARAGE_ADMIN` και μπλοκάρεται με HTTP 409 Conflict αν υπάρχουν επισκευές. Το ίδιο ακριβώς επίπεδο ασφαλείας πρέπει να εφαρμοστεί και στο όχημα.

2. **Διαγραφή Ιστορικής Επισκευής (`WorkOrderController::delete`):**
   Ο μηχανικός μπορεί επί του παρόντος να διαγράψει οποιαδήποτε εντολή εργασίας, συμπεριλαμβανομένων ιστορικών επισκευών που παραδόθηκαν (`status === 'delivered'`) μήνες ή χρόνια πριν. Η διαγραφή ιστορικού πρέπει να επιτρέπεται αποκλειστικά στον διαχειριστή του συνεργείου, επιτρέποντας στον μηχανικό να διαγράφει μόνο ενεργές / πρόχειρες εργασίες (π.χ. λάθος check-in της ημέρας).

3. **Αλλοίωση Παραδοθείσας Επισκευής (`WorkOrderController::update`):**
   Μια παραδοθείσα επισκευή (`status === 'delivered'`) αποτελεί κλειστή, τιμολογημένη εργασία με καταγεγραμμένα χιλιόμετρα και οικονομικά στοιχεία. Στην τρέχουσα υλοποίηση, ένας μηχανικός μπορεί μέσω `PATCH /api/garage/work-orders/{id}` να αλλάξει την τιμή (`price`), τα χιλιόμετρα (`odometerKm`), την περιγραφή ή την κατάσταση μιας παλιάς παραδοθείσας επισκευής. Οι αλλαγές σε παραδοθείσες επισκευές πρέπει να κλειδωθούν για τον μηχανικό και να επιτρέπονται μόνο στον `ROLE_GARAGE_ADMIN`.

---

## 2. Λειτουργικές Προδιαγραφές (Functional Specifications)

### 2.1 Προστασία Διαγραφής Οχήματος (`VehicleController::delete`)
* **Endpoint:** `DELETE /api/garage/vehicles/{id}`
* **Περιορισμός Ρόλου:** Προσθήκη attribute `#[IsGranted('ROLE_GARAGE_ADMIN')]` στη μέθοδο `delete`. Αν κληθεί από μηχανικό (`ROLE_MECHANIC`), απορρίπτεται με **HTTP 403 Forbidden**.
* **Guardrail Υπαρχουσών Επισκευών:**
  - Πριν τη διαγραφή, ελέγχεται αν υπάρχουν συσχετισμένες εντολές εργασίας:
    ```php
    $workOrderCount = $this->workOrderRepository->count(['vehicle' => $vehicle, 'garage' => $garage]);
    ```
  - Αν `$workOrderCount > 0`, η αίτηση απορρίπτεται με **HTTP 409 Conflict**:
    ```json
    {
      "error": "Δεν είναι δυνατή η διαγραφή οχήματος με καταγεγραμμένο ιστορικό επισκευών.",
      "code": "VEHICLE_HAS_WORK_ORDERS",
      "workOrderCount": 3
    }
    ```
* **Επιτυχής Διαγραφή:** Μόνο αν `$workOrderCount === 0` και ο χρήστης είναι `ROLE_GARAGE_ADMIN`, εκτελείται `$entityManager->remove($vehicle)` επιστρέφοντας **HTTP 200 OK** (`{"message": "Vehicle successfully deleted"}`).

---

### 2.2 Προστασία Διαγραφής Ιστορικής Επισκευής (`WorkOrderController::delete`)
* **Endpoint:** `DELETE /api/garage/work-orders/{id}`
* **Κανόνας Ασφαλείας:**
  - Ορίζεται ως ιστορική κάθε εργασία με `status === 'delivered'` ή με καταγεγραμμένο `pickedUpAt !== null`.
  - Αν η εργασία είναι ιστορική και ο χρήστης **δεν** διαθέτει ρόλο `ROLE_GARAGE_ADMIN`, η αίτηση απορρίπτεται με **HTTP 403 Forbidden**:
    ```json
    {
      "error": "Μόνο ο διαχειριστής του συνεργείου μπορεί να διαγράψει ιστορικές επισκευές.",
      "code": "HISTORICAL_REPAIR_DELETE_FORBIDDEN"
    }
    ```
  - Αν η εργασία είναι ενεργή (`status !== 'delivered'` και `pickedUpAt === null`), επιτρέπεται η διαγραφή τόσο από `ROLE_MECHANIC` όσο και από `ROLE_GARAGE_ADMIN` (για άμεση ακύρωση λανθασμένης σημερινής καταχώρησης).

---

### 2.3 Κλείδωμα Τροποποίησης Παραδοθείσας Επισκευής (`WorkOrderController::update`)
* **Endpoint:** `PATCH /api/garage/work-orders/{id}`
* **Κανόνας Ασφαλείας:**
  - Αν η εργασία έχει ήδη κατάσταση `status === 'delivered'` και ο χρήστης **δεν** διαθέτει ρόλο `ROLE_GARAGE_ADMIN`:
  - Αν το αίτημα επιχειρεί να τροποποιήσει κρίσιμα πεδία (`price`, `odometerKm`, `status`, `date`, `description`):
    Η αίτηση απορρίπτεται άμεσα με **HTTP 403 Forbidden**:
    ```json
    {
      "error": "Δεν επιτρέπεται η τροποποίηση παραδοθείσας επισκευής από μηχανικό.",
      "code": "DELIVERED_REPAIR_UPDATE_LOCKED"
    }
    ```
  - Μόνο ο `ROLE_GARAGE_ADMIN` έχει δικαίωμα να διορθώσει οικονομικά στοιχεία, χιλιόμετρα ή να αλλάξει κατάσταση σε ήδη παραδοθείσα επισκευή.

---

### 2.4 Multi-Tenant Scoping (Απομόνωση Συνεργείου)
* Όλοι οι έλεγχοι παραμένουν αυστηρά scoped στο συνεργείο του συνδεδεμένου χρήστη (`$user->getGarage()`).
* Αιτήματα για οχήματα ή εργασίες άλλου συνεργείου επιστρέφουν πάντοτε **HTTP 404 Not Found**, αποτρέποντας οποιαδήποτε διαρροή πληροφορίας.

---

## 3. Προδιαγραφές Ελέγχων (Testing Requirements)

Δημιουργία λειτουργικών δοκιμών (`WebTestCase`) που να καλύπτουν πλήρως όλα τα νέα σενάρια ασφαλείας:

1. **Διαγραφή Οχήματος (`VehicleController::delete`):**
   - Μηχανικός (`ROLE_MECHANIC`) επιχειρεί διαγραφή -> **HTTP 403 Forbidden**.
   - Admin (`ROLE_GARAGE_ADMIN`) επιχειρεί διαγραφή οχήματος με καταγεγραμμένες επισκευές -> **HTTP 409 Conflict** (`VEHICLE_HAS_WORK_ORDERS`).
   - Admin επιχειρεί διαγραφή οχήματος χωρίς επισκευές -> **HTTP 200 OK**.
   - Cross-tenant διαγραφή οχήματος -> **HTTP 404 Not Found**.

2. **Διαγραφή Εντολής Εργασίας (`WorkOrderController::delete`):**
   - Μηχανικός διαγράφει ενεργή εργασία (`checked_in` / `in_progress`) -> **HTTP 200 OK**.
   - Μηχανικός επιχειρεί διαγραφή ιστορικής εργασίας (`delivered`) -> **HTTP 403 Forbidden** (`HISTORICAL_REPAIR_DELETE_FORBIDDEN`).
   - Admin διαγράφει ιστορική εργασία (`delivered`) -> **HTTP 200 OK**.
   - Cross-tenant διαγραφή εργασίας -> **HTTP 404 Not Found**.

3. **Τροποποίηση Εντολής Εργασίας (`WorkOrderController::update`):**
   - Μηχανικός τροποποιεί ενεργή εργασία (`checked_in` / `in_progress`) -> **HTTP 200 OK**.
   - Μηχανικός επιχειρεί να αλλάξει τιμή ή χιλιόμετρα σε παραδοθείσα εργασία (`delivered`) -> **HTTP 403 Forbidden** (`DELIVERED_REPAIR_UPDATE_LOCKED`).
   - Admin τροποποιεί παραδοθείσα εργασία (`delivered`) -> **HTTP 200 OK**.
