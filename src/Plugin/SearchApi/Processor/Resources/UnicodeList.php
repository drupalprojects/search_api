<?php
/**
 * Created by PhpStorm.
 * User: nick.veenhof
 * Date: 6/14/14
 * Time: 5:59 PM
 */

namespace Drupal\search_api\Plugin\SearchApi\Processor\Resources;


interface UnicodeList {

  /**
   * Returns the regular expression string
   *
   * @return string
   */
  public function getRegularExpression();

} 