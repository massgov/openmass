<?php

namespace Drupal\mass_migration\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Given the publishing frequency value returns the corresponding key.
 *
 * @MigrateProcessPlugin(
 *   id = "mass_migration_get_organization_title",
 *   handle_multiples = TRUE
 * )
 */
class GetOrganizationTitle extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($org_number, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Check for empty value.
    if (empty($org_number)) {
      return FALSE;
    }

    // Return the corresponding title.
    return $this->getTitle($org_number);
  }

  /**
   * Return the title of an organization given the id.
   *
   * @param int $org_number
   *   Organization Id.
   *
   * @return bool|mixed
   *   Title of organization or FALSE.
   */
  private function getTitle($org_number) {
    $organizations = [
      "762" => "Civil Rights Division",
      "763" => "Commission on Bullying Prevention",
      "764" => "Commission on the Status of Women",
      "765" => "Consumer Protection Division",
      "766" => "Criminal History Systems Board",
      "785" => "State 911 Department",
      "431" => "* ANF - Office of Administration and Finance",
      "426" => "Essex District Attorney",
      "136" => "Appellate Tax Board",
      "151" => "Board of Bar Examiners",
      "156" => "Board of Registration in Medicine",
      "759" => "Architectural Access Board",
      "760" => "Bureau of Air and Waste",
      "761" => "Bureau of Local Assessment",
      "768" => "Department of Conservation and Recreation",
      "769" => "* PSS - Office of Public Safety and Security",
      "770" => "Group Insurance Commission",
      "773" => "Massachusetts Workforce Development System",
      "775" => "Office of the Attorney General",
      "778" => "Health Policy Commission",
      "767" => "Department of Cash Management",
      "771" => "Massachusetts Department of Higher Education",
      "772" => "Massachusetts National Guard",
      "774" => "Motor Vehicle Crimes Program",
      "777" => "State Ethics Commission",
      "779" => "Joint Task Force on the Underground Economy and Employee Misclassification",
      "780" => "Massachusetts Teachers' Retirement System",
      "781" => "Natural Heritage and Endangered Species Program",
      "782" => "Non-Profit Organizations/Public Charities Division",
      "783" => "Office of Child Care Services",
      "784" => "Office of the State Auditor",
      "786" => "State Board of Retirement",
      "787" => "State Ethics Commission",
      "789" => "Division of Open Government",
      "790" => "Division of Marine Fisheries",
      "757" => "Appeals Court",
      "776" => "Trial Court",
      "788" => "Department of Labor Relations",
      "171" => "Cape and Islands District Attorney",
      "381" => "Division of Health Care Finance and Policy",
      "566" => "Massachusetts Gaming Commission",
      "166" => "Bureau of State Office Buildings",
      "176" => "Civil Service Commission",
      "181" => "Office of Coastal Zone Management",
      "186" => "Massachusetts Commission on Judicial Conduct",
      "201" => "Department of Career Services",
      "206" => "Department of Children and Families",
      "216" => "Department of Correction",
      "221" => "Department of Criminal Justice Information Services",
      "226" => "Department of Developmental Services",
      "231" => "Department of Early Education and Care",
      "241" => "Department of Energy Resources",
      "246" => "Department of Environmental Protection",
      "251" => "Department of Fire Services",
      "256" => "Department of Fish and Game",
      "261" => "Department of Housing and Community Development",
      "266" => "Department of Industrial Accidents",
      "276" => "Department of Labor Standards",
      "281" => "Department of Mental Health",
      "286" => "Department of Public Health",
      "291" => "Department of Public Safety",
      "236" => "Department of Elementary and Secondary Education",
      "196" => "Department of Agricultural Resources",
      "191" => "Comptroller",
      "581" => "Office of Consumer Affairs and Business Regulation",
      "758" => "* EEA - Office of Energy and Environmental Affairs",
      "301" => "Department of Revenue",
      "296" => "Department of Public Utilities",
      "351" => "Division of Banks",
      "311" => "Department of Telecommunications and Cable",
      "316" => "Department of Transitional Assistance",
      "321" => "Department of Unemployment Assistance",
      "326" => "Department of Veterans' Services",
      "331" => "Department of Youth Services",
      "336" => "Disabled Persons Protection Commission",
      "346" => "Division of Apprentice Standards",
      "356" => "Division of Capital Asset Management and Maintenance",
      "361" => "Division of Child Support Enforcement",
      "371" => "Division of Fisheries and Wildlife",
      "386" => "Division of Insurance",
      "396" => "Division of Professional Licensure",
      "401" => "Division of Standards",
      "436" => "* EOE - Executive Office of Education",
      "441" => "Office of Elder Affairs",
      "451" => "* HHS - Office of Health and Human Services",
      "456" => "* HED - Office of Housing and Economic Development",
      "461" => "* LWD - Office of Labor and Workforce Development",
      "471" => "Harbormaster Training Council",
      "486" => "Human Resources Division",
      "491" => "Human Service Transportation Office",
      "496" => "Massachusetts Bays National Estuary Program",
      "501" => "Massachusetts Commission Against Discrimination",
      "506" => "Commission for the Blind",
      "511" => "Massachusetts Commission for the Deaf and Hard of Hearing",
      "516" => "Commission on the Status of Women",
      "536" => "Massachusetts Developmental Disabilities Council",
      "541" => "Massachusetts District Attorneys Association",
      "551" => "Massachusetts Emergency Management Agency",
      "561" => "Massachusetts Environmental Policy Act Office",
      "591" => "Massachusetts Office of International Trade and Investment",
      "601" => "Massachusetts Permit Regulatory Office",
      "606" => "Massachusetts Rehabilitation Commission",
      "616" => "Massachusetts Water Pollution Abatement Trust",
      "521" => "Massachusetts Court System",
      "526" => "Massachusetts Court System Law Library",
      "476" => "Health Disparities Council",
      "756" => "Virtual Gateway",
      "736" => "State Retiree Benefits Trust Fund",
      "731" => "State Library",
      "686" => "Parole Board",
      "661" => "Office of the Commissioner of Probation",
      "571" => "Massachusetts Office for Victim Assistance",
      "576" => "Massachusetts Office of Business Development",
      "586" => "MassIT",
      "596" => "Massachusetts Office on Disability",
      "641" => "Office of Court Management",
      "626" => "MassHealth",
      "556" => "Massachusetts Environmental Police",
      "16" => "Division of Professional Licensure",
      "26" => "Board of Registration of Massage Therapy",
      "51" => "Board of Registration of Chiropractors",
      "81" => "Office of Private Occupational School Education",
      "66" => "Board of Examiners of Sheet Metal Workers",
      "96" => "The Board of Registration of Psychologists",
      "126" => "Board of Registration of Funeral Directors and Embalmers",
      "161" => "Board of Registration of Hazardous Waste Site Cleanup Professionals",
      "306" => "Massachusetts State Police",
      "146" => "Berkshire District Attorney",
      "21" => "Board of Registration of Cosmetology and Barbering",
      "706" => "Soldiers' Home in Holyoke",
      "666" => "Governor's Office",
      "746" => "Office of the Treasurer and Receiver General",
      "791" => "Division of Ecological Restoration",
      "792" => "Division of Administrative Law Appeals",
      "621" => "Massachusetts Workforce Development Board",
      "631" => "Municipal Police Training Committee",
      "636" => "Office for Refugees and Immigrants",
      "646" => "Office of Jury Commissioner",
      "651" => "Office of the Chief Medical Examiner",
      "656" => "Office of the Child Advocate",
      "671" => "Office of the Inspector General",
      "676" => "Open Meeting Law Advisory Commission",
      "681" => "Operational Services Division",
      "691" => "Personal Care Attendant Workforce Council",
      "696" => "Public Employee Retirement Administration Commission",
      "701" => "Sex Offender Registry Board",
      "741" => "Supreme Judicial Court",
      "406" => "* PSS - Office of Public Safety and Security",
      "416" => "Essex County Sheriff's Department",
      "421" => "* EEA - Office of Energy and Environmental Affairs",
      "411" => "Massachusetts Sheriffs' Association",
      "800" => "Division of Local Services",
      "801" => "Alcoholic Beverages Control Commission (ABCC)",
      "802" => "Massachusetts Department of Transportation (DOT)",
      "803" => "Highway Division"
    ];

    return empty($organizations[$org_number]) ? 'STUB ' . intval($org_number) : $organizations[$org_number];
  }

}
