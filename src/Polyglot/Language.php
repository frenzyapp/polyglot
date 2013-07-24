<?php
namespace Polyglot;

use Illuminate\Container\Container;
use Underscore\Methods\ArraysMethods as Arrays;
use Underscore\Methods\StringMethods as String;

/**
 * General localization helpers
 */
class Language
{
  /**
   * Build the language class
   *
   * @param Container $app
   */
  public function __construct(Container $app)
  {
    $this->app = $app;
  }

  ////////////////////////////////////////////////////////////////////
  /////////////////////////// TRANSLATIONS ///////////////////////////
  ////////////////////////////////////////////////////////////////////

  /**
   * Injects a translated title into the page
   *
   * @param  string $title A page or a title
   *
   * @return string        A title Blade section
   */
  public function title($title = null)
  {
    $title = $this->app['lang']->get($title, null)->get();

    return Section::inject('title', $title);
  }

  /**
   * Translates a string with various fallbacks points
   *
   * @param  string $key      The key/string to translate
   * @param  string $fallback A fallback to display
   *
   * @return string           A translated string
   */
  public function translate($key, $fallback = null)
  {
    if (!$fallback) $fallback = $key;

    // Search for the key itself
    $translation = $this->app['lang']->get($key)->get(null, '');

    // If not found, search in the field attributes
    if (!$translation) {
      $translation = $this->app['lang']->get('validation.attributes.'.$key)
        ->get(null, $fallback);
    }

    // If we found a translations array
    if (is_array($translation)) $translation = $fallback;
    return ucfirst($translation);
  }

  ////////////////////////////////////////////////////////////////////
  ///////////////////////////// HELPERS //////////////////////////////
  ////////////////////////////////////////////////////////////////////

  /**
   * Whether a given language is the current one
   *
   * @param  string $language The language to check
   *
   * @return boolean
   */
  public function isActive($language)
  {
    return $language == $this->current();
  }

  /**
   * Returns the current language being used
   *
   * @return string A language index
   */
  public function current()
  {
    $base = trim($this->app['url']->basebase(), '/');
    $current = $this->app['config']->get('application.language');

    $language = preg_replace('#'.$base.'/([a-z]{2})/(.+)#', '$1', $this->app['url']->basecurrent());
    if ($language and $language != $current) Language::set($language);
    if (String::length($language) != 2) $language = $current;
    return $language;
  }

  /**
   * Change the current language
   *
   * @param string $language The language to change to
   *
   * @return string
   */
  public function set($language)
  {
    if (!static::valid($language)) return false;
    return $this->app['config']->set('application.language', $language);
  }

  /**
   * Get all available languages
   *
   * @return array An array of languages
   */
  public function available()
  {
    return $this->app['config']->get('application.languages');
  }

  /**
   * Check whether a language is valid or not
   *
   * @param string $language The language
   * @return boolean
   */
  public function valid($language)
  {
    return in_array($language, static::available());
  }

  ////////////////////////////////////////////////////////////////////
  /////////////////////////////// URLS ///////////////////////////////
  ////////////////////////////////////////////////////////////////////

  /**
   * Get the URL to switch language, keeping the current page or not
   *
   * @param  string  $lang  The new language
   * @param  boolean $reset Whether navigation should be reset
   * @return string         An URL
   */
  public function to($lang, $reset = false)
  {
    // Reset path or not
    if($reset) return $this->app['url']->basebase().'/'.$lang;

    // Check for invalid languages
    if(!static::valid($lang)) $lang = static::current();

    // Compute base URL with language added
    $base    = trim($this->app['url']->basebase(), '/');
    $base   .= '/'.$lang.'/';
    $current = $this->app['url']->basecurrent();

    // Replace base with localized base
    $final = preg_replace('#' .$this->app['url']->basebase(). '/?#', $base, $current);

    return $final;
  }

  ////////////////////////////////////////////////////////////////////
  /////////////////////////////// TASKS //////////////////////////////
  ////////////////////////////////////////////////////////////////////

  /**
   * Sets the locale according to the current language
   *
   * @param  string $language A language string to use
   * @return
   */
  public function locale($language = false)
  {
    // If nothing was given, just use current language
    if(!$language) $language = self::current();

    // Base table of languages
    $locales = array(
      'de' => array('de_DE.UTF8','de_DE@euro','de_DE','de','ge'),
      'fr' => array('fr_FR.UTF8','fr_FR','fr'),
      'es' => array('es_ES.UTF8','es_ES','es'),
      'it' => array('it_IT.UTF8','it_IT','it'),
      'pt' => array('pt_PT.UTF8','pt_PT','pt'),
      'zh' => array('zh_CN.UTF8','zh_CN','zh'),
      'en' => array('en_US.UTF8','en_US','en'),
    );

    // Set new locale
    setlocale(LC_ALL, Arrays::get($locales, $language, array('en_US.UTF8','en_US','en')));

    return setlocale(LC_ALL, 0);
  }

  ////////////////////////////////////////////////////////////////////
  ////////////////////////////// ELOQUENT ////////////////////////////
  ////////////////////////////////////////////////////////////////////

  /**
   * Apply the correct language constraint to an array of eager load relationships
   *
   * @return array An array of relationships
   */
  public function eager()
  {
    $language = static::current();
    $relationships = array();

    // Get arguments
    $eager = func_get_args();
    if (sizeof($eager) == 1 and is_array($eager[0])) $eager = $eager[0];

    foreach ($eager as $r) {
      if (!String::find($r, 'lang')) $relationships[] = $r;
      else {
        $relationships[$r] = function($query) use ($language) {
          $query->where_lang($language);
        };
      }
    }

    return $relationships;
  }
}
