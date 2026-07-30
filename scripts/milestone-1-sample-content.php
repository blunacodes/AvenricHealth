<?php

/**
 * @file
 * One-time scaffold: minimal sample content proving Milestone 1's actual
 * acceptance criteria - that Service, Provider and Location cross-reference
 * each other correctly, end to end.
 *
 * Run via `ddev drush scr scripts/milestone-1-sample-content.php`.
 *
 * Deliberately NOT full population (12 providers, 6 locations, etc. - that's
 * Milestone 7's job per the roadmap, not this one). Also deliberately not
 * config: taxonomy terms and nodes are content, not configuration.
 * `drush config:export` never captures them - config/sync gives a fresh
 * site the *shape* of the content model (content types, fields), but zero
 * actual data. Reproducing demo content needs its own mechanism, which is
 * exactly what this script is: safe to re-run after any fresh install
 * (guarded by title/name lookups, same idempotency pattern as the
 * structural scaffold script).
 */

use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;

/**
 * Load a taxonomy term by name within a vocabulary, or create it.
 */
function ac_term(string $vocabulary, string $name): Term {
  $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => $vocabulary, 'name' => $name]);
  if ($existing) {
    return reset($existing);
  }
  $term = Term::create(['vid' => $vocabulary, 'name' => $name]);
  $term->save();
  echo "  term: $vocabulary/$name\n";
  return $term;
}

/**
 * Load a node by type + title, or create it with the given field values.
 */
function ac_node(string $type, string $title, array $values = []): Node {
  $existing = \Drupal::entityTypeManager()->getStorage('node')
    ->loadByProperties(['type' => $type, 'title' => $title]);
  if ($existing) {
    return reset($existing);
  }
  $node = Node::create(['type' => $type, 'title' => $title] + $values);
  $node->save();
  echo "  node: $type/$title\n";
  return $node;
}

echo "Terms:\n";
$type_hospital = ac_term('location_type', 'Hospital');
$type_clinic = ac_term('location_type', 'Clinic');
$type_imaging = ac_term('location_type', 'Imaging Center');
$spec_cardiology = ac_term('specialty', 'Cardiology');
$spec_primary_care = ac_term('specialty', 'Primary Care');
$spec_radiology = ac_term('specialty', 'Radiology');
$cat_cardiology = ac_term('service_category', 'Cardiology');
$cat_primary_care = ac_term('service_category', 'Primary Care');
$cat_imaging = ac_term('service_category', 'Imaging Services');
$lang_english = ac_term('language', 'English');

echo "Locations:\n";
$loc_downtown = ac_node('location', 'Downtown Medical Center', [
  'field_location_type' => ['target_id' => $type_hospital->id()],
  'field_address' => '100 Main Street',
  'field_city' => 'Springfield',
  'field_state' => 'IL',
  'field_postal_code' => '62701',
  'field_phone' => '555-0100',
]);
$loc_westside = ac_node('location', 'Westside Family Clinic', [
  'field_location_type' => ['target_id' => $type_clinic->id()],
  'field_address' => '450 West Avenue',
  'field_city' => 'Springfield',
  'field_state' => 'IL',
  'field_postal_code' => '62702',
  'field_phone' => '555-0102',
]);
$loc_north = ac_node('location', 'North Imaging Center', [
  'field_location_type' => ['target_id' => $type_imaging->id()],
  'field_address' => '900 North Boulevard',
  'field_city' => 'Springfield',
  'field_state' => 'IL',
  'field_postal_code' => '62703',
  'field_phone' => '555-0103',
]);

echo "Providers:\n";
$prov_vasquez = ac_node('provider', 'Dr. Elena Vasquez', [
  'field_professional_title' => 'Cardiologist',
  'field_credentials' => 'MD, FACC',
  'field_specialties' => [['target_id' => $spec_cardiology->id()]],
  'field_languages' => [['target_id' => $lang_english->id()]],
  'field_related_locations' => [['target_id' => $loc_downtown->id()]],
  'field_accepting_patients' => TRUE,
  'field_telehealth' => FALSE,
]);
$prov_chen = ac_node('provider', 'Dr. Marcus Chen', [
  'field_professional_title' => 'Family Medicine Physician',
  'field_credentials' => 'MD',
  'field_specialties' => [['target_id' => $spec_primary_care->id()]],
  'field_languages' => [['target_id' => $lang_english->id()]],
  'field_related_locations' => [['target_id' => $loc_westside->id()]],
  'field_accepting_patients' => TRUE,
  'field_telehealth' => TRUE,
]);
$prov_okafor = ac_node('provider', 'Dr. Amara Okafor', [
  'field_professional_title' => 'Radiologist',
  'field_credentials' => 'MD, PhD',
  'field_specialties' => [['target_id' => $spec_radiology->id()]],
  'field_languages' => [['target_id' => $lang_english->id()]],
  'field_related_locations' => [['target_id' => $loc_north->id()]],
  'field_accepting_patients' => FALSE,
  'field_telehealth' => FALSE,
]);

echo "Services:\n";
ac_node('service', 'Cardiology', [
  'field_summary' => 'Comprehensive heart and vascular care.',
  'field_service_category' => ['target_id' => $cat_cardiology->id()],
  'field_related_providers' => [['target_id' => $prov_vasquez->id()]],
  'field_related_locations' => [['target_id' => $loc_downtown->id()]],
  'field_featured' => TRUE,
]);
ac_node('service', 'Primary Care', [
  'field_summary' => 'Whole-family primary and preventive care.',
  'field_service_category' => ['target_id' => $cat_primary_care->id()],
  'field_related_providers' => [['target_id' => $prov_chen->id()]],
  'field_related_locations' => [['target_id' => $loc_westside->id()]],
  'field_featured' => TRUE,
]);
ac_node('service', 'Imaging Services', [
  'field_summary' => 'Diagnostic imaging including X-ray, CT and MRI.',
  'field_service_category' => ['target_id' => $cat_imaging->id()],
  'field_related_providers' => [['target_id' => $prov_okafor->id()]],
  'field_related_locations' => [['target_id' => $loc_north->id()]],
  'field_featured' => FALSE,
]);

// Backfill the reverse direction: Provider/Location -> Service. Node IDs
// weren't known until the Service nodes above were created, so this can
// only happen now, same "create base bundles, backfill cross-references"
// pattern used for the field definitions themselves.
$services = \Drupal::entityTypeManager()->getStorage('node')
  ->loadByProperties(['type' => 'service']);
$service_by_title = [];
foreach ($services as $service) {
  $service_by_title[$service->label()] = $service;
}

$prov_vasquez->set('field_related_services', [['target_id' => $service_by_title['Cardiology']->id()]]);
$prov_vasquez->save();
$prov_chen->set('field_related_services', [['target_id' => $service_by_title['Primary Care']->id()]]);
$prov_chen->save();
$prov_okafor->set('field_related_services', [['target_id' => $service_by_title['Imaging Services']->id()]]);
$prov_okafor->save();

$loc_downtown->set('field_related_services', [['target_id' => $service_by_title['Cardiology']->id()]]);
$loc_downtown->set('field_related_providers', [['target_id' => $prov_vasquez->id()]]);
$loc_downtown->save();
$loc_westside->set('field_related_services', [['target_id' => $service_by_title['Primary Care']->id()]]);
$loc_westside->set('field_related_providers', [['target_id' => $prov_chen->id()]]);
$loc_westside->save();
$loc_north->set('field_related_services', [['target_id' => $service_by_title['Imaging Services']->id()]]);
$loc_north->set('field_related_providers', [['target_id' => $prov_okafor->id()]]);
$loc_north->save();

echo "Basic Page:\n";
ac_node('page', 'About', ['body' => ['value' => '<p>Avenric Health is a fictional healthcare network built as a Drupal portfolio project.</p>', 'format' => 'basic_html']]);

echo "Done.\n";
