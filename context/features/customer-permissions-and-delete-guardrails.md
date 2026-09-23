# Feature Specification: Customer Permissions & Delete Guardrails (`customer-permissions-and-delete-guardrails`)

## 1. Context & Business Rationale (Σκοπός & Αιτιολόγηση)

Στην τρέχουσα υλοποίηση του `CustomerController` (`/api/garage/customers`), το endpoint διαγραφής (`DELETE /{id}`) και ενημέρωσης (`PATCH /{id}`) προστατεύονται σε επίπεδο κλάσης με:
```php
#[IsGranted('ROLE_MECHANIC')]
```
Επειδή ο ρόλος `ROLE_GARAGE_ADMIN` κληρονομεί τον `ROLE_MECHANIC`, οποιοσδήποτε υπάλληλος/μηχανικός συνεργείου έχει τη δυνατότητα να διαγράψει οριστικά έναν πελάτη.

### Το Πρόβλημα
Η οντότητα `Customer` συνδέεται με σχέσεις `OneToMany` με:
- `Vehicle` (`cascade: ['remove'], orphanRemoval: true`)
- `WorkOrder` (`cascade: ['remove'], orphanRemoval: true`)
- `Notification` (`cascade: ['remove']`)

Όταν ένας πελάτης διαγράφεται με `DELETE`:
1. Διαγράφονται οριστικά **όλα τα οχήματά του**.
2. Διαγράφονται οριστικά **όλες οι εντολές εργασίας (ιστορικό επισκευών, περιγραφές, κόστη, εισπράξεις)**.
3. Προκαλείται απώλεια κρίσιμων λογιστικών, φορολογικών και ιστορικών δεδομένων του συνεργείου.

### Στόχος Feature
1. **Διαχωρισμός Ρόλων (RBAC Authorization)**:
   - **Ενημέρωση (`PATCH /api/garage/customers/{id}`)**: Επιτρέπεται σε `ROLE_MECHANIC` και `ROLE_GARAGE_ADMIN`, καθώς στην καθημερινή ροή ο μηχανικός χρειάζεται να διορθώσει τηλέφωνο, όνομα ή σημειώσεις.
   - **Διαγραφή (`DELETE /api/garage/customers/{id}`)**: Επιτρέπεται **ΑΠΟΚΛΕΙΣΤΙΚΑ** σε `ROLE_GARAGE_ADMIN`. Απλοί μηχανικοί απορρίπτονται με **HTTP 403 Forbidden**.
2. **Προστασία Ακεραιότητας Δεδομένων (Delete Guardrail)**:
   - Απαγόρευση διαγραφής πελάτη εάν υπάρχουν συνδεδεμένες εντολές εργασίας (`WorkOrder`).
   - Σε περίπτωση απόπειρας διαγραφής πελάτη με ιστορικό, άμεση επιστροφή **HTTP 409 Conflict** με κωδικό σφάλματος `CUSTOMER_HAS_WORK_ORDERS`.
   - Επιτρέπεται η διαγραφή μόνο για πελάτες χωρίς καταγεγραμμένες επισκευές (π.χ. λανθασμένη/διπλότυπη καταχώρηση χωρίς εργασίες).

---

## 2. Λειτουργικές Προδιαγραφές (Functional Specifications)

### 2.1 Δικαιώματα Ενημέρωσης (`PATCH /api/garage/customers/{id}`)
- **Επιτρεπόμενοι Ρόλοι**: `ROLE_MECHANIC`, `ROLE_GARAGE_ADMIN`.
- **Έλεγχος Tenant Isolation**: Ο πελάτης πρέπει να ανήκει στο συνεργείο του συνδεδεμένου χρήστη (`$user->getGarage()`).
- **Απόκριση**: `200 OK` με τα ενημερωμένα στοιχεία του πελάτη.

### 2.2 Δικαιώματα Διαγραφής (`DELETE /api/garage/customers/{id}`)
- **Επιτρεπόμενος Ρόλος**: `#[IsGranted('ROLE_GARAGE_ADMIN')]`.
- **Απόρριψη Μηχανικού**:
  - Αίτημα από χρήστη με μόνο `ROLE_MECHANIC` -> **HTTP 403 Forbidden** (`Access Denied`).
- **Έλεγχος Ύπαρξης Εντολών Εργασίας (Work Orders Guardrail)**:
  Πριν από την εκτέλεση `$entityManager->remove($customer)`:
  ```php
  $workOrderCount = $this->workOrderRepository->count(['customer' => $customer, 'garage' => $garage]);
  if ($workOrderCount > 0) {
      return $this->json([
          'error' => 'Δεν είναι δυνατή η διαγραφή πελάτη με καταγεγραμμένο ιστορικό επισκευών.',
          'code' => 'CUSTOMER_HAS_WORK_ORDERS',
          'workOrderCount' => $workOrderCount,
      ], Response::HTTP_CONFLICT);
  }
  ```
- **Επιτυχής Διαγραφή**:
  - Αν `workOrderCount === 0`, επιτρέπεται η διαγραφή του πελάτη (και τυχόν οχημάτων του που δεν έχουν εργασίες).
  - Απόκριση: `200 OK` με μήνυμα `['message' => 'Customer successfully deleted']`.

---

## 3. Αρχεία προς Τροποποίηση

1. **`src/Controller/Api/Garage/CustomerController.php`**:
   - Inject του `WorkOrderRepository`.
   - Προσθήκη `#[IsGranted('ROLE_GARAGE_ADMIN')]` στην action `delete()`.
   - Προσθήκη ελέγχου `$workOrderRepository->count(...)` και επιστροφή HTTP 409 Conflict με `CUSTOMER_HAS_WORK_ORDERS`.
2. **`tests/Garage/CustomerVehicleManagementTest.php`**:
   - Προσθήκη ελέγχου ότι `ROLE_MECHANIC` λαμβάνει 403 Forbidden κατά το `DELETE /api/garage/customers/{id}`.
   - Προσθήκη ελέγχου ότι `ROLE_GARAGE_ADMIN` λαμβάνει 409 Conflict όταν διαγράφει πελάτη με υπάρχουσες εργασίες.
   - Προσθήκη ελέγχου ότι `ROLE_GARAGE_ADMIN` μπορεί να διαγράψει πελάτη χωρίς εργασίες.
   - Επιβεβαίωση ότι `ROLE_MECHANIC` διατηρεί πλήρη πρόσβαση σε `PATCH /api/garage/customers/{id}`.

---

## 4. Σχέδιο Επαλήθευσης (Verification Plan)

### Automated Tests
1. **Δοκιμή RBAC (Mechanic vs Admin)**:
   - `ROLE_MECHANIC` εκτελεί `DELETE /api/garage/customers/{id}` -> Επιβεβαίωση `403 Forbidden`.
   - `ROLE_GARAGE_ADMIN` εκτελεί `DELETE /api/garage/customers/{id}` σε πελάτη με εργασίες -> Επιβεβαίωση `409 Conflict`, code `CUSTOMER_HAS_WORK_ORDERS`.
   - `ROLE_GARAGE_ADMIN` εκτελεί `DELETE /api/garage/customers/{id}` σε πελάτη χωρίς εργασίες -> Επιβεβαίωση `200 OK`.
2. **Δοκιμή Update**:
   - `ROLE_MECHANIC` εκτελεί `PATCH /api/garage/customers/{id}` -> Επιβεβαίωση `200 OK`.
3. **Εκτέλεση Όλων των Tests**:
   - `docker compose exec -T php php bin/phpunit`
