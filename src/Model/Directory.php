<?php

namespace App\Model;

use NetBS\CoreBundle\Utils\StrUtil;

class Directory
{
    private $path;

    private $config;

    private $medias = null;

    public function __construct($path, GalerieConfig $config)
    {
        $this->path     = $path;
        $this->config   = $config;
    }

    /**
     * @return Directory[]
     */
    public function getChildren() {

        $dirnames   = array_filter(glob($this->path . "/" . '*'), 'is_dir');
        $names = [];
        $numbers = [];
        foreach($dirnames as $dirname) {
            $data = explode("/", $dirname);
            $last = array_pop($data);
            if (is_numeric($last)) $numbers[$dirname] = $last;
            else $names[$dirname] = $last;
        }

        asort($names);
        arsort($numbers);

        $dirnames = array_merge(array_keys($names), array_keys($numbers));

        return array_values(array_map(function($name) {
            return new Directory($name, $this->config);
        }, $dirnames));
    }

    /**
     * @return Media[]
     */
    public function getMedias() {

        if(is_array($this->medias))
            return $this->medias;

        $filenames  = [];
        $extensions = $this->config->getImageExtensions();
        foreach($this->config->getImageExtensions() as $ext)
            $extensions[] = strtoupper($ext);

        foreach($extensions as $ext)
            $filenames = array_merge($filenames, array_filter(glob($this->path . '/*' . $ext), 'is_file'));

        $this->medias = array_values(array_map(function($name) {
            return new Media($name, $this->config);
        }, $filenames));

        return $this->medias;
    }

    /**
     * @return null|Media
     */
    public function getThumbnail() {

        $medias = $this->getMedias();

        if(count($medias) > 0)
            return reset($medias);

        foreach($this->getChildren() as $child)
            if($thumb = $child->getThumbnail())
                return $thumb;

        return null;
    }

    public function getName() {

        $segments   = explode('/', $this->path);

        return end($segments);
    }

    public function getDescription() {

        $filenames = $this->config->getDescriptionFilename();
        foreach ($filenames as $filename) {
            $descriptionFilePath = $this->path . "/" . $filename;
            if (is_file($descriptionFilePath)) return file_get_contents($descriptionFilePath);
        }
        return null;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    public function getRelativePath() {

        return str_replace($this->config->getFullMappedDirectory(), "", $this->path);
    }


    public function getHashPath() {

        return self::hash2($this->getRelativePath());
    }

    public static function hash2($str) {
        $data = explode('/', $str);
        $data = array_map(function ($item) { return preg_replace("/^galerie-/", '', StrUtil::slugify($item));}, $data);
        return implode('/', $data);
    }
}
