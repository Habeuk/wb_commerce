<?php

namespace Drupal\wb_commerce\Services;

use Drupal\hbkcolissimochrono\Services\ColissimoDefaultSettings as ServicesColissimoDefaultSettings;
use Stephane888\Debug\Repositories\ConfigDrupal;

/**
 * Service description.
 */
class ColissimoDefaultSettings extends ServicesColissimoDefaultSettings {


  public function getSettings() {
    // dd("hello world");
    return ConfigDrupal::config("hbkcolissimochrono.settings");
  }
}
