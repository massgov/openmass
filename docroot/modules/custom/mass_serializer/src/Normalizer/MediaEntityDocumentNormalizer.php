<?php

namespace Drupal\mass_serializer\Normalizer;

use Drupal\Core\Url;
use Drupal\Component\Utility\Unicode;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\serialization\Normalizer\ContentEntityNormalizer;
use Drupal\taxonomy\TermStorage;
use stdClass;

/**
 * Converts the Drupal entity object structures to a normalized array.
 */
class MediaEntityDocumentNormalizer extends ContentEntityNormalizer {

  /**
   * The list of fields multi-value.
   *
   * @var array
   */
  private $multiValueFields = [
    'field_additional_info',
    'field_creator',
    'field_subjects',
    'field_tags',
  ];

  /**
   * The list of included fields.
   *
   * @var array
   */
  private $includedFields = [
    'field_title',
    'field_description',
    'field_contributing_organization',
    'field_contact_name',
    'field_contact_information',
    'field_publishing_frequency',
    'field_license',
    'field_start_date',
    'field_end_date',
    'field_geographic_place',
    'field_language',
    'field_subjects',
    'field_tags',
    'field_rights',
    'field_data_dictionary',
    'field_conform',
    'field_system_of_records',
    'field_data_quality',
    'changed',
    'created',
    'uuid',
    'field_alternative_title',
    'field_creator',
    'field_content_type',
    'field_additional_info',
    'field_link_related_content',
    'field_internal_notes',
    'field_part_of',
    'field_oclc_number',
    'field_upload_file',
    'field_link_classic_massgov',
    'field_file_migration_id',
    'field_checksum',
  ];

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = MediaInterface::class;

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    // If we aren't dealing with an object or the format is not supported return
    // now.
    if (!is_object($data) || !$this->checkFormat($format)) {
      return FALSE;
    }
    // This custom normalizer should be supported for "document" media entities.
    if ($data instanceof MediaInterface && $data->bundle() == 'document') {
      return TRUE;
    }
    // Otherwise, this normalizer does not support the $data object.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $attributes = parent::normalize($object, $format, $context);
    /** @var \Drupal\taxonomy\TermStorage $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    // Keep only fields defined in context.
    $attributes = array_intersect_key($attributes, array_flip($this->includedFields));

    // Eliminate unnecessary arrays in single value fields to get valid json
    // directly. UUID for example.
    foreach ($attributes as $key => &$attribute) {
      if (is_array($attribute) && count($attribute) == 1 && !in_array($key, $this->multiValueFields)) {
        $attribute = current($attribute);
      }
    }

    // Rename keys to needed values.
    $this->renameKeys($attributes);

    $attributes['@type'] = 'dcat:Dataset';
    // Description must be at least 1 characters long.
    $attributes['description'] = empty($attributes['description']) ? 'N/A' : $attributes['description'];
    /** @var \Drupal\media\MediaInterface $object */
    $attributes['identifier'] = $object->toUrl('canonical', ['absolute' => TRUE])->toString();

    // Set publisher.
    $publisher = $attributes['field_contributing_organization'];
    if (!empty($publisher)) {
      /** @var \Drupal\taxonomy\Entity\Term $term */
      $term = $term_storage->load($publisher['target_id']);
      $attributes['publisher']['name'] = $term->getName();
      // Check for 'subOrganizationOf'.
      $this->setPublisherParents($attributes['publisher'], $publisher['target_id'], $term_storage);
    }
    else {
      $attributes['publisher'] = new stdClass();
    }
    unset($attributes['field_contributing_organization']);

    // Access Level - Defaults to public.
    $attributes['accessLevel'] = empty($attributes['accessLevel'])
      ? 'public'
      : (is_array($attributes['accessLevel'])
      ? Unicode::strtolower(current($attributes['accessLevel']))
      : Unicode::strtolower($attributes['accessLevel']));

    // Contact Point.
    $contact_term = $attributes['field_contact_name'];
    $contact_info = $attributes['field_contact_information'];
    // The properties contactPoint and fn are required.
    $attributes['contactPoint']['@type'] = 'vcard:Contact';
    $attributes['contactPoint']['fn'] = 'N/A';
    if (!empty($contact_term)) {
      $contact = $term_storage->load($contact_term['target_id']);
      $attributes['contactPoint']['fn'] = $contact->getName();
    }
    if (!empty($contact_info)) {
      $attributes['contactPoint']['hasEmail'] = "mailto:" . $contact_info;
    }
    unset($attributes['field_contact_name']);
    unset($attributes['field_contact_information']);

    // Publishing frequency.
    if (!empty($attributes['accrualPeriodicity']) || $attributes['accrualPeriodicity'] == 0) {
      // We need to map the current value to an ISO 8601 equivalent.
      $attributes['accrualPeriodicity'] = $this->getIsoFrequency($attributes['accrualPeriodicity']);
    }
    else {
      unset($attributes['accrualPeriodicity']);
    }

    // License.
    if (!empty($attributes['license'])) {
      $term = $term_storage->load($attributes['license']['target_id']);
      $license_text = $term->getName();
      $attributes['license'] = $this->getOpenLicense($license_text);
    }
    // If we ended up with an empty string license, then remove it from json.
    if (empty($attributes['license'])) {
      unset($attributes['license']);
    }

    // Temporal coverage.
    $start_date = $attributes['field_start_date'];
    // If End Date is empty then use Start Date as the end of period.
    $end_date = empty($attributes['field_end_date']) ? $attributes['field_start_date'] : $attributes['field_end_date'];
    if (!empty($start_date)) {
      // Transform time to ISO 8601 format.
      $start_date = date('c', strtotime($start_date));
      $end_date = date('c', strtotime($end_date));
      $attributes['temporal'] = $start_date . '/' . $end_date;
    }
    unset($attributes['field_start_date']);
    unset($attributes['field_end_date']);

    // Language. Needs to be an object according to schema.
    if (!empty($attributes['field_language'])) {
      $attributes['language'] = [];
      $term = $term_storage->load($attributes['field_language']['target_id']);
      $attributes['language'][] = $term->getDescription();
    }
    unset($attributes['field_language']);

    // Subjects / Theme.
    if (!empty($attributes['field_subjects'])) {
      $attributes['theme'] = [];
      foreach ($attributes['field_subjects'] as $subject) {
        $term = $term_storage->load($subject['target_id']);
        $attributes['theme'][] = $term->getName();
      }
    }
    unset($attributes['field_subjects']);

    // Set Tags / keyword.
    if (!empty($attributes['field_tags'])) {
      $attributes['keyword'] = [];
      foreach ($attributes['field_tags'] as $tag) {
        $term = $term_storage->load($tag['target_id']);
        $attributes['keyword'][] = $term->getName();
      }
    }
    unset($attributes['field_tags']);

    // Rights.
    // Only shows if Access Level = Restricted or Private.
    if (empty($attributes['rights']) || $attributes['accessLevel'] == 'public') {
      unset($attributes['rights']);
    }

    // Data Dictionary.
    if (empty($attributes['describedBy'])) {
      unset($attributes['describedBy']);
    }

    // Conforms to.
    // A string is required so we cannot include the title as an array.
    // @see https://project-open-data.cio.gov/v1.1/schema/#conformsTo
    if (!empty($attributes['field_conform']) && !empty($attributes['field_conform']['uri'])) {
      if (FALSE !== strpos($attributes['field_conform']['uri'], 'entity:')) {
        $attributes['conformsTo'] = Url::fromUri($attributes['field_conform']['uri'])->setAbsolute()->toString();
      }
      else {
        $attributes['conformsTo'] = $attributes['field_conform']['uri'];
      }
    }
    unset($attributes['field_conform']);

    // System of records.
    // A string is required so we cannot include the title as an array.
    // @see https://project-open-data.cio.gov/v1.1/schema/#systemOfRecords
    if (!empty($attributes['field_system_of_records']) && !empty($attributes['field_system_of_records']['uri'])) {
      if (FALSE !== strpos($attributes['field_system_of_records']['uri'], 'entity:')) {
        $attributes['systemOfRecords'] = Url::fromUri($attributes['field_system_of_records']['uri'])->setAbsolute()->toString();
      }
      else {
        $attributes['systemOfRecords'] = $attributes['field_system_of_records']['uri'];
      }
    }
    unset($attributes['field_system_of_records']);

    // Data Quality.
    $attributes['dataQuality'] = empty($attributes['dataQuality']) ? FALSE : TRUE;

    // Modified and Released date in ISO 8601 format.
    $attributes['issued'] = date('c', $attributes['issued']);
    $attributes['modified'] = date('c', $attributes['modified']);

    // Alternative Title.
    if (empty($attributes['altTitle'])) {
      unset($attributes['altTitle']);
    }

    // Author.
    if (!empty($attributes['field_creator'])) {
      $attributes['author'] = [];
      foreach ($attributes['field_creator'] as $author) {
        $term = $term_storage->load($author['target_id']);
        $attributes['author'][] = $term->getName();
      }
    }
    unset($attributes['field_creator']);

    // Content Type.
    if (!empty($attributes['field_content_type'])) {
      $term = $term_storage->load($attributes['field_content_type']['target_id']);
      $attributes['contentType'] = $term->getName();
    }
    unset($attributes['field_content_type']);

    // Additional Info.
    if (!empty($attributes['field_additional_info'])) {
      $attributes['addInfo'] = [];
      foreach ($attributes['field_additional_info'] as $info) {
        $attributes['addInfo'][] = [
          'key' => empty($info['key']) ? '' : $info['key'],
          'value' => empty($info['value']) ? '' : $info['value'],
        ];
      }
    }
    unset($attributes['field_additional_info']);

    // Related Content. Only one value is allowed in field config.
    if (!empty($attributes['field_link_related_content'])) {
      if (!empty($attributes['field_link_related_content']['title'])) {
        $attributes['relatedContent']['title'] = $attributes['field_link_related_content']['title'];
      }
      $attributes['relatedContent']['url'] = $attributes['field_link_related_content']['uri'];
    }
    unset($attributes['field_link_related_content']);

    // Internal Notes.
    if (empty($attributes['intNote'])) {
      unset($attributes['intNote']);
    }

    // Is part of.
    if (!empty($attributes['field_part_of'])) {
      $document = Media::load($attributes['field_part_of']['target_id']);
      $attributes['isPartOf'] = $document->get('field_title')->value;
    }
    unset($attributes['field_part_of']);

    // OCLC Number.
    if (empty($attributes['oclc'])) {
      unset($attributes['oclc']);
    }

    // Distribution. One file is in the document.
    if (!empty($attributes['field_upload_file']) && !empty($attributes['field_upload_file']['target_id'])) {
      $file = File::load($attributes['field_upload_file']['target_id']);
      if ($file) {
        $url_parts = explode('.', $file->url());
        $attributes['distribution'][] = [
          '@type' => 'dcat:Distribution',
          'title' => $attributes['title'],
          'downloadURL' => $file->url(),
          'format' => end($url_parts),
          'mediaType' => $file->getMimeType(),
        ];
      }
      if (!empty($attributes['field_link_classic_massgov'])) {
        $attributes['distribution'][0]['legacyUrl'] = $attributes['field_link_classic_massgov']['uri'];
      }
      if (isset($attributes['temporal']) && !empty($attributes['temporal'])) {
        $attributes['distribution'][0]['temporal'] = $attributes['temporal'];
      }
      if (isset($attributes['oclc']) && !empty($attributes['oclc'])) {
        $attributes['distribution'][0]['oclc'] = $attributes['oclc'];
      }
      if (isset($attributes['spatial']) && !empty($attributes['spatial'])) {
        $attributes['distribution'][0]['spatial'] = $attributes['spatial'];
      }
      if (!empty($attributes['field_file_migration_id'])) {
        $attributes['distribution'][0]['migrationID'] = $attributes['field_file_migration_id'];
      }
      if (!empty($attributes['field_checksum'])) {
        $attributes['distribution'][0]['checksum'] = $attributes['field_checksum'];
      }
    }
    unset($attributes['field_file_migration_id']);
    unset($attributes['field_checksum']);
    unset($attributes['field_link_classic_massgov']);
    unset($attributes['field_upload_file']);

    // Re-sort the array after our new addition.
    ksort($attributes);
    // Return the $attributes with our new value.
    return $attributes;
  }

  /**
   * Change the key names according to the open data needed names.
   *
   * @param array $attributes
   *   Attributes with key names to change.
   */
  private function renameKeys(array &$attributes) {
    $key_names = [
      'field_title' => 'title',
      'field_description' => 'description',
      'field_publishing_frequency' => 'accrualPeriodicity',
      'field_license' => 'license',
      'field_geographic_place' => 'spatial',
      'field_rights' => 'rights',
      'field_data_dictionary' => 'describedBy',
      'field_data_quality' => 'dataQuality',
      'created' => 'issued',
      'changed' => 'modified',
      'field_alternative_title' => 'altTitle',
      'field_internal_notes' => 'intNote',
      'field_oclc_number' => 'oclc',
    ];
    foreach ($key_names as $old_key => $new_key) {
      if (isset($attributes[$old_key])) {
        $attributes[$new_key] = $attributes[$old_key];
        unset($attributes[$old_key]);
      }
    }
  }

  /**
   * Given the numeric DKAN frequency returns the ISO 8601 equivalent value.
   *
   * If the the equivalent ISO value is not found then the DKAN string will
   * be returned.
   *
   * @param int $frequency
   *   The DKAN numeric value for the publishing frequency.
   *
   * @return string
   *   The ISO 8601 accrualPeriodicity or DKAN frequency if equivalent
   *   value is not found.
   *
   * @see https://project-open-data.cio.gov/iso8601_guidance
   */
  private function getIsoFrequency($frequency) {
    // ISO 8601 accrualPeriodicity values.
    $accrualPeriodicity = [
      'Once' => 'irregular',
      'Decennial' => 'R/P10Y',
      'Quadrennial' => 'R/P4Y',
      'Annual' => 'R/P1Y',
      'Bimonthly' => 'R/P2M or R/P0.5M',
      'Semiweekly' => 'R/P3.5D',
      'Daily' => 'R/P1D',
      'Biweekly' => 'R/P2W or R/P0.5W',
      'Semiannual' => 'R/P6M',
      'Biennial' => 'R/P2Y',
      'Triennial' => 'R/P3Y',
      'Three times a week' => 'R/P0.33W',
      'Three times a month' => 'R/P0.33M',
      'Continuously updated' => 'R/PT1S',
      'Monthly' => 'R/P1M',
      'Quarterly' => 'R/P3M',
      'Semimonthly' => 'R/P0.5M',
      'Three times a year' => 'R/P4M',
      'Weekly' => 'R/P1W',
      'Hourly' => 'R/PT1H',
    ];

    // Get the string equivalent value of DKAN numeric value.
    $dkan_frequencies = FieldConfig::loadByName('media', 'document', 'field_publishing_frequency')->getSettings()['allowed_values'];
    // Load the DKAN string in frequency.
    $frequency = $dkan_frequencies[$frequency];

    if (array_key_exists($frequency, $accrualPeriodicity)) {
      return $accrualPeriodicity[$frequency];
    }

    // Some strings has a difference as Annual and Annually, so try adding 'ly'.
    $frequency_mod = $frequency . 'ly';
    if (array_key_exists($frequency_mod, $accrualPeriodicity)) {
      return $accrualPeriodicity[$frequency_mod];
    }

    // Try without 'ly' ... or the two last characters.
    $frequency_mod = Unicode::substr($frequency, 0, -2);
    if (array_key_exists($frequency_mod, $accrualPeriodicity)) {
      return $accrualPeriodicity[$frequency_mod];
    }

    // Accepted Values line.
    return $frequency;
  }

  /**
   * Given the Document license returns the equivalent Open License Url.
   *
   * @param string $license
   *   License as defined in mass.
   *
   * @return string
   *   Equivalent Open License Url or empty string.
   *
   * @see https://project-open-data.cio.gov/open-licenses
   */
  private function getOpenLicense($license) {
    $licenses = [
      'License Not Specified' => '',
      'Attribution NonCommercial NoDerivatives 4.0 International' => 'https://creativecommons.org/licenses/by-nc-nd/4.0/',
      'Creative Commons Attribution' => 'https://creativecommons.org/licenses/by/4.0/',
      'Creative Commons Attribution Share-Alike' => 'https://creativecommons.org/licenses/by-sa/4.0/',
      'Creative Commons CCZero' => 'https://creativecommons.org/publicdomain/zero/1.0/',
      'Creative Commons Non-Commercial (Any)' => 'https://creativecommons.org/licenses/by-nc/3.0/us/',
      'GNU Free Documentation License' => 'http://www.gnu.org/licenses/fdl-1.3.en.html',
      'Open Data Commons Attribution License' => 'http://opendatacommons.org/licenses/by/1.0/',
      'Open Data Commons Open Database License (OdbL)' => 'http://opendatacommons.org/licenses/odbl/1.0/',
      'Open Data Commons Public Domain Dedication and Licence (PDDL)' => 'http://opendatacommons.org/licenses/pddl/1.0/',
      'UK Open Government Licence (OGL)' => 'https://www.nationalarchives.gov.uk/doc/open-government-licence/version/2/',
      'Other (Attribution)' => 'https://docs.digital.mass.gov/files/licenses/other-attribution',
      'Other (Non-Commercial)' => 'https://docs.digital.mass.gov/files/licenses/other-non-commercial',
      'Other (Not Open)' => 'https://docs.digital.mass.gov/files/licenses/other-not-open',
      'Other (Open)' => 'https://docs.digital.mass.gov/files/licenses/other-open',
      'Other (Public Domain)' => 'https://docs.digital.mass.gov/files/licenses/other-public-domain',
    ];

    return empty($licenses[$license]) ? '' : $licenses[$license];
  }

  /**
   * Set recursively the parents of the publisher.
   *
   * The parent values goes into $attributes['publisher']['subOrganizationOf'].
   *
   * @param array $publisher
   *   The attributes that conform the json output.
   * @param int $tid
   *   Taxonomy id of the term.
   * @param \Drupal\taxonomy\TermStorage $term_storage
   *   TermStorage instance.
   *
   * @return bool
   *   FALSE when there is no parent.
   */
  private function setPublisherParents(array &$publisher, $tid, TermStorage $term_storage) {
    /** @var \Drupal\taxonomy\Entity\Term $parent */
    $parent = current($term_storage->loadParents($tid));

    if (empty($parent)) {
      return FALSE;
    }

    // Only one parent is allowed in taxonomy.
    $publisher['subOrganizationOf'] = ['name' => $parent->getName()];
    return $this->setPublisherParents($publisher['subOrganizationOf'], $parent->id(), $term_storage);
  }

}
