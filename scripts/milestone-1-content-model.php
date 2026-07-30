<?php

/**
 * @file
 * One-time scaffold: Milestone 1 content architecture.
 *
 * Run via `ddev drush scr scripts/milestone-1-content-model.php`. Builds the
 * taxonomies, content types and fields described in the project roadmap
 * section 7-8, then the result is exported to config/sync/ as the real
 * source of truth. This script is not part of the running site - it is a
 * repeatable record of how the content model was built, safe to re-run
 * (every creation is guarded by an existence check).
 */

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\image\Entity\ImageStyle;
use Drupal\pathauto\Entity\PathautoPattern;

/**
 * Create a vocabulary if it doesn't already exist.
 */
function ah_vocabulary(string $vid, string $name): void {
  if (Vocabulary::load($vid)) {
    return;
  }
  Vocabulary::create(['vid' => $vid, 'name' => $name])->save();
  echo "  vocabulary: $vid\n";
}

/**
 * Create a content type if it doesn't already exist.
 */
function ah_content_type(string $type, string $name): void {
  if (NodeType::load($type)) {
    return;
  }
  NodeType::create(['type' => $type, 'name' => $name])->save();
  echo "  content type: $type\n";
}

/**
 * Add a standard "body" (long text + summary) field to a bundle.
 *
 * node_add_body_field() is deprecated as of Drupal 11.3 (removed in 12) and,
 * in this Drupal 11.4 install, throws outright: it looks up a 'body' field
 * storage that the 'standard' profile no longer pre-creates (this profile
 * ships with zero default content types, unlike older Drupal). Building the
 * field directly via ah_field(), same as every other field in this script,
 * sidesteps the deprecated helper entirely.
 */
function ah_body_field(string $bundle): void {
  ah_field(
    'node',
    $bundle,
    'body',
    'text_with_summary',
    'Body',
    [],
    ['display_summary' => TRUE],
  );
}

/**
 * Create a field storage + field instance on a bundle if not already present.
 *
 * @param string $entity_type
 *   e.g. 'node'.
 * @param string $bundle
 *   e.g. 'service'.
 * @param string $field_name
 *   e.g. 'field_hero_image'.
 * @param string $type
 *   Field type, e.g. 'string', 'text_long', 'entity_reference'.
 * @param string $label
 *   Human-readable field label.
 * @param array $storage_settings
 *   Settings for FieldStorageConfig (e.g. target_type for entity reference).
 * @param array $field_settings
 *   Settings for FieldConfig (e.g. handler_settings target_bundles).
 * @param int $cardinality
 *   -1 for unlimited, otherwise a positive integer.
 */
function ah_field(
  string $entity_type,
  string $bundle,
  string $field_name,
  string $type,
  string $label,
  array $storage_settings = [],
  array $field_settings = [],
  int $cardinality = 1,
): void {
  if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => $type,
      'settings' => $storage_settings,
      'cardinality' => $cardinality,
    ])->save();
  }
  if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => $label,
      'settings' => $field_settings,
    ])->save();
    echo "    field: $bundle.$field_name\n";
  }

  // A field exists as data structure the moment FieldConfig is saved, but
  // Drupal does NOT show it on the edit form or the rendered page until a
  // display component is explicitly assigned. Unconditional (not just for
  // newly-created fields) and idempotent, so re-running this script after
  // adding widget/formatter logic backfills displays for fields that were
  // already created in an earlier run.
  [$widget, $formatter] = ah_widget_formatter($type, $storage_settings);
  $display_repository = \Drupal::service('entity_display.repository');
  $display_repository->getFormDisplay($entity_type, $bundle)
    ->setComponent($field_name, ['type' => $widget])
    ->save();
  $display_repository->getViewDisplay($entity_type, $bundle)
    ->setComponent($field_name, ['type' => $formatter])
    ->save();
}

/**
 * Pick a reasonable default widget/formatter pair for a field type.
 *
 * @return array{0: string, 1: string}
 *   [widget plugin ID, formatter plugin ID].
 */
function ah_widget_formatter(string $type, array $storage_settings): array {
  return match (TRUE) {
    $type === 'entity_reference' && ($storage_settings['target_type'] ?? NULL) === 'media' =>
      ['media_library_widget', 'entity_reference_entity_view'],
    $type === 'entity_reference' =>
      ['entity_reference_autocomplete', 'entity_reference_label'],
    $type === 'text_with_summary' => ['text_textarea_with_summary', 'text_default'],
    $type === 'text_long' => ['text_textarea', 'text_default'],
    $type === 'string_long' => ['string_textarea', 'basic_string'],
    $type === 'string' => ['string_textfield', 'string'],
    $type === 'boolean' => ['boolean_checkbox', 'boolean'],
    $type === 'decimal' => ['number', 'number_decimal'],
    $type === 'datetime' => ['datetime_default', 'datetime_default'],
    $type === 'list_string' => ['options_select', 'list_default'],
    $type === 'link' => ['link_default', 'link'],
    default => ['string_textfield', 'string'],
  };
}

function ah_entity_ref(
  string $bundle,
  string $field_name,
  string $label,
  string $target_type,
  array $target_bundles,
  int $cardinality = 1,
): void {
  ah_field(
    'node',
    $bundle,
    $field_name,
    'entity_reference',
    $label,
    ['target_type' => $target_type],
    [
      'handler' => $target_type === 'node' ? 'default:node' : 'default:taxonomy_term',
      'handler_settings' => ['target_bundles' => array_combine($target_bundles, $target_bundles)],
    ],
    $cardinality,
  );
}

function ah_media_ref(string $bundle, string $field_name, string $label, string $media_bundle, int $cardinality = 1): void {
  ah_field(
    'node',
    $bundle,
    $field_name,
    'entity_reference',
    $label,
    ['target_type' => 'media'],
    [
      'handler' => 'default:media',
      'handler_settings' => ['target_bundles' => [$media_bundle => $media_bundle]],
    ],
    $cardinality,
  );
}

/**
 * Create a Pathauto URL alias pattern scoped to a single node bundle.
 */
function ah_pathauto_pattern(string $id, string $label, string $bundle, string $pattern): void {
  if (PathautoPattern::load($id)) {
    return;
  }
  $entity = PathautoPattern::create([
    'id' => $id,
    'label' => $label,
    'type' => 'canonical_entities:node',
    'pattern' => $pattern,
  ]);
  $entity->addSelectionCondition([
    'id' => 'entity_bundle:node',
    'bundles' => [$bundle => $bundle],
    'negate' => FALSE,
    'context_mapping' => ['node' => 'node'],
  ]);
  $entity->save();
  echo "  pathauto pattern: $id\n";
}

// ---------------------------------------------------------------------
// Taxonomies.
// ---------------------------------------------------------------------
echo "Taxonomies:\n";
ah_vocabulary('service_category', 'Service Category');
ah_vocabulary('specialty', 'Specialty');
ah_vocabulary('location_type', 'Location Type');
ah_vocabulary('health_topic', 'Health Topic');
ah_vocabulary('news_category', 'News Category');
ah_vocabulary('event_type', 'Event Type');
ah_vocabulary('audience', 'Audience');
ah_vocabulary('language', 'Language');

// ---------------------------------------------------------------------
// Location (created first: Provider and Service both reference it).
// ---------------------------------------------------------------------
echo "Location:\n";
ah_content_type('location', 'Location');
ah_entity_ref('location', 'field_location_type', 'Location Type', 'taxonomy_term', ['location_type']);
ah_field('node', 'location', 'field_address', 'string', 'Address');
ah_field('node', 'location', 'field_city', 'string', 'City');
ah_field('node', 'location', 'field_state', 'string', 'State');
ah_field('node', 'location', 'field_postal_code', 'string', 'Postal Code');
ah_field('node', 'location', 'field_phone', 'string', 'Phone');
ah_field('node', 'location', 'field_hours', 'text_long', 'Hours');
ah_field('node', 'location', 'field_latitude', 'decimal', 'Latitude', ['precision' => 10, 'scale' => 6]);
ah_field('node', 'location', 'field_longitude', 'decimal', 'Longitude', ['precision' => 10, 'scale' => 6]);
ah_field('node', 'location', 'field_parking_info', 'text_long', 'Parking Information');
ah_field('node', 'location', 'field_accessibility_info', 'text_long', 'Accessibility Information');
ah_media_ref('location', 'field_hero_image', 'Hero Image', 'image');
ah_field('node', 'location', 'field_seo_description', 'string_long', 'SEO Description');

// ---------------------------------------------------------------------
// Provider (references Location now; Service reference backfilled below).
// ---------------------------------------------------------------------
echo "Provider:\n";
ah_content_type('provider', 'Provider');
ah_field('node', 'provider', 'field_professional_title', 'string', 'Professional Title');
ah_field('node', 'provider', 'field_credentials', 'string', 'Credentials');
ah_media_ref('provider', 'field_profile_image', 'Profile Image', 'image');
ah_field('node', 'provider', 'field_biography', 'text_long', 'Biography');
ah_entity_ref('provider', 'field_specialties', 'Specialties', 'taxonomy_term', ['specialty'], -1);
ah_entity_ref('provider', 'field_languages', 'Languages', 'taxonomy_term', ['language'], -1);
ah_field('node', 'provider', 'field_education', 'text_long', 'Education');
ah_field('node', 'provider', 'field_certifications', 'text_long', 'Certifications');
ah_entity_ref('provider', 'field_related_locations', 'Related Locations', 'node', ['location'], -1);
ah_field('node', 'provider', 'field_phone', 'string', 'Phone');
ah_field('node', 'provider', 'field_accepting_patients', 'boolean', 'Accepting New Patients');
ah_field('node', 'provider', 'field_telehealth', 'boolean', 'Telehealth Available');
ah_field('node', 'provider', 'field_seo_description', 'string_long', 'SEO Description');

// Backfill: Location -> Provider (mutual reference, Provider now exists).
ah_entity_ref('location', 'field_related_providers', 'Related Providers', 'node', ['provider'], -1);

// ---------------------------------------------------------------------
// Service (references Provider and Location, both now exist).
// ---------------------------------------------------------------------
echo "Service:\n";
ah_content_type('service', 'Service');
ah_body_field('service');
ah_field('node', 'service', 'field_summary', 'text_long', 'Summary');
ah_media_ref('service', 'field_hero_image', 'Hero Image', 'image');
ah_entity_ref('service', 'field_service_category', 'Service Category', 'taxonomy_term', ['service_category']);
ah_field('node', 'service', 'field_conditions_treated', 'string', 'Conditions Treated', [], [], -1);
ah_entity_ref('service', 'field_related_providers', 'Related Providers', 'node', ['provider'], -1);
ah_entity_ref('service', 'field_related_locations', 'Related Locations', 'node', ['location'], -1);
// Simplification: spec lists "Related FAQs" with no dedicated FAQ content
// type defined elsewhere in the roadmap. Modeled as free text for the MVP;
// revisit as a proper entity reference if/when a FAQ content type exists.
ah_field('node', 'service', 'field_faqs', 'text_long', 'Related FAQs');
ah_field('node', 'service', 'field_contact_phone', 'string', 'Contact Phone');
ah_field('node', 'service', 'field_cta_link', 'link', 'Call-to-Action Link');
ah_field('node', 'service', 'field_seo_description', 'string_long', 'SEO Description');
ah_field('node', 'service', 'field_featured', 'boolean', 'Featured Status');

// Backfill: Provider/Location -> Service (Service now exists).
ah_entity_ref('provider', 'field_related_services', 'Related Services', 'node', ['service'], -1);
ah_entity_ref('location', 'field_related_services', 'Related Services', 'node', ['service'], -1);

// ---------------------------------------------------------------------
// Health Resource.
// ---------------------------------------------------------------------
echo "Health Resource:\n";
ah_content_type('health_resource', 'Health Resource');
ah_body_field('health_resource');
ah_field('node', 'health_resource', 'field_summary', 'text_long', 'Summary');
ah_entity_ref('health_resource', 'field_topic', 'Topic', 'taxonomy_term', ['health_topic']);
ah_field('node', 'health_resource', 'field_reviewed_date', 'datetime', 'Reviewed Date', ['datetime_type' => 'date']);
ah_field('node', 'health_resource', 'field_reviewer', 'string', 'Reviewer Name or Role');
ah_entity_ref('health_resource', 'field_related_services', 'Related Services', 'node', ['service'], -1);
ah_entity_ref('health_resource', 'field_related_providers', 'Related Providers', 'node', ['provider'], -1);
ah_media_ref('health_resource', 'field_featured_image', 'Featured Image', 'image');
ah_media_ref('health_resource', 'field_documents', 'Downloadable Documents', 'document', -1);
ah_field('node', 'health_resource', 'field_seo_description', 'string_long', 'SEO Description');

// Backfill: Service -> Health Resource.
ah_entity_ref('service', 'field_related_health_resources', 'Related Health Resources', 'node', ['health_resource'], -1);

// ---------------------------------------------------------------------
// News Article.
// ---------------------------------------------------------------------
echo "News Article:\n";
ah_content_type('news_article', 'News Article');
ah_body_field('news_article');
ah_field('node', 'news_article', 'field_summary', 'text_long', 'Summary');
ah_field('node', 'news_article', 'field_publication_date', 'datetime', 'Publication Date', ['datetime_type' => 'date']);
ah_field('node', 'news_article', 'field_author', 'string', 'Author');
ah_entity_ref('news_article', 'field_news_category', 'News Category', 'taxonomy_term', ['news_category']);
ah_media_ref('news_article', 'field_featured_image', 'Featured Image', 'image');
ah_entity_ref('news_article', 'field_related_services', 'Related Services', 'node', ['service'], -1);
ah_entity_ref('news_article', 'field_related_locations', 'Related Locations', 'node', ['location'], -1);
ah_field('node', 'news_article', 'field_seo_description', 'string_long', 'SEO Description');

// ---------------------------------------------------------------------
// Event.
// ---------------------------------------------------------------------
echo "Event:\n";
ah_content_type('event', 'Event');
ah_field('node', 'event', 'field_summary', 'text_long', 'Summary');
ah_field('node', 'event', 'field_description', 'text_long', 'Description');
ah_field('node', 'event', 'field_start_date', 'datetime', 'Start Date and Time', ['datetime_type' => 'datetime']);
ah_field('node', 'event', 'field_end_date', 'datetime', 'End Date and Time', ['datetime_type' => 'datetime']);
ah_entity_ref('event', 'field_event_type', 'Event Type', 'taxonomy_term', ['event_type']);
ah_entity_ref('event', 'field_audience', 'Audience', 'taxonomy_term', ['audience'], -1);
ah_entity_ref('event', 'field_location', 'Location', 'node', ['location']);
ah_field('node', 'event', 'field_online_link', 'link', 'Online Event Link');
ah_field('node', 'event', 'field_registration_link', 'link', 'Registration Link');
ah_media_ref('event', 'field_featured_image', 'Featured Image', 'image');
ah_field('node', 'event', 'field_cost', 'string', 'Cost');
ah_entity_ref('event', 'field_related_service', 'Related Service', 'node', ['service']);

// ---------------------------------------------------------------------
// Alert.
// ---------------------------------------------------------------------
echo "Alert:\n";
ah_content_type('alert', 'Alert');
ah_field('node', 'alert', 'field_alert_message', 'text_long', 'Alert Message');
ah_field(
  'node',
  'alert',
  'field_alert_level',
  'list_string',
  'Alert Level',
  ['allowed_values' => ['informational' => 'Informational', 'advisory' => 'Advisory', 'urgent' => 'Urgent']],
);
ah_field('node', 'alert', 'field_start_date', 'datetime', 'Start Date', ['datetime_type' => 'date']);
ah_field('node', 'alert', 'field_end_date', 'datetime', 'End Date', ['datetime_type' => 'date']);
ah_field('node', 'alert', 'field_link', 'link', 'Link');
ah_field('node', 'alert', 'field_active', 'boolean', 'Active Status');

// ---------------------------------------------------------------------
// Basic Page. Used for About, Leadership, Careers, Contact, Accessibility
// statement, Privacy notice, Terms - static governance content, just a
// title and a body, no custom fields.
// ---------------------------------------------------------------------
echo "Basic Page:\n";
ah_content_type('page', 'Basic Page');
ah_body_field('page');

// ---------------------------------------------------------------------
// Image styles, matching the reusable page-building components in the
// roadmap (Hero, Card grid, Featured providers): core ships thumbnail /
// medium / large / wide, none scaled+cropped for these specific uses.
// ---------------------------------------------------------------------
echo "Image styles:\n";
$styles = [
  'hero' => ['label' => 'Hero (1600x600 crop)', 'width' => 1600, 'height' => 600],
  'card' => ['label' => 'Card (480x320 crop)', 'width' => 480, 'height' => 320],
  'avatar' => ['label' => 'Avatar (300x300 crop)', 'width' => 300, 'height' => 300],
];
foreach ($styles as $id => $spec) {
  if (ImageStyle::load($id)) {
    continue;
  }
  $style = ImageStyle::create(['name' => $id, 'label' => $spec['label']]);
  $style->addImageEffect([
    'id' => 'image_scale_and_crop',
    'data' => ['width' => $spec['width'], 'height' => $spec['height']],
  ]);
  $style->save();
  echo "  image style: $id\n";
}

// ---------------------------------------------------------------------
// Pathauto URL patterns, one per bundle, matching the primary navigation
// sections (Services, Providers, Locations, Health Resources, News,
// Events). Basic Page and Alert are left on Drupal's default /node/N path
// for now - Basic Page aliases are typically set by hand per page (About,
// Careers, etc.), and Alerts aren't intended to be browsed at a URL.
// ---------------------------------------------------------------------
echo "Pathauto patterns:\n";
ah_pathauto_pattern('service', 'Service', 'service', '/services/[node:title]');
ah_pathauto_pattern('provider', 'Provider', 'provider', '/providers/[node:title]');
ah_pathauto_pattern('location', 'Location', 'location', '/locations/[node:title]');
ah_pathauto_pattern('health_resource', 'Health Resource', 'health_resource', '/health-resources/[node:title]');
ah_pathauto_pattern('news_article', 'News Article', 'news_article', '/news/[node:title]');
ah_pathauto_pattern('event', 'Event', 'event', '/events/[node:title]');

echo "Done.\n";
