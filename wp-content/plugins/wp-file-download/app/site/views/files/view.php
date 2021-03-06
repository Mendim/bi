<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

use Joomunited\WPFramework\v1_0_5\Application;
use Joomunited\WPFramework\v1_0_5\View;
use Joomunited\WPFramework\v1_0_5\Utilities;
use Joomunited\WPFramework\v1_0_5\Model;

defined('ABSPATH') || die();

/**
 * Class WpfdViewFiles
 */
class WpfdViewFiles extends View
{

    /**
     * Display files
     *
     * @param string $tpl Template name
     *
     * @return void
     */
    public function render($tpl = null)
    {
        $id_category   = Utilities::getInt('id');
        $root_category = Utilities::getInt('rootcat');
        $modelCat      = $this->getModel('category');
        $category      = $modelCat->getCategory($id_category);
        $rootcategory  = $modelCat->getCategory($root_category);
        $modelFiles    = $this->getModel('files');

        $app             = Application::getInstance('Wpfd');
        $path_wpfdhelper = $app->getPath() . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'helpers';
        $path_wpfdhelper .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
        require_once $path_wpfdhelper;
        if (!WpfdHelper::checkCategoryAccess($category)) {
            $content           = new stdClass();
            $content->files    = array();
            $content->category = new stdClass();
            echo json_encode($content);
            die();
        }

        $modelTokens = Model::getInstance('tokens');
        $token       = $modelTokens->getOrCreateNew();

        $orderCol    = Utilities::getInput('orderCol', 'GET', 'none');
        $orderDir    = Utilities::getInput('orderDir', 'GET', 'none');
        $ordering    = $orderCol !== null ? $orderCol : $category->ordering;
        $orderingdir = $orderDir !== null ? $orderDir : $category->orderingdir;

        $description = json_decode($category->description, true);
        $lstAllFile  = null;
        if (!empty($description) && isset($description['refToFile'])) {
            if (isset($description['refToFile'])) {
                $listCatRef = $description['refToFile'];
                $lstAllFile = $this->getAllFileRef($modelFiles, $listCatRef, $ordering, $orderingdir);
            }
        }
        /**
         * Filter to check category source
         *
         * @param integer Term id
         *
         * @return string
         *
         * @internal
         *
         * @ignore
         */
        $categoryFrom = apply_filters('wpfdAddonCategoryFrom', $id_category);
        if ($categoryFrom === 'googleDrive') {
            $files             = apply_filters(
                'wpfdAddonGetListGoogleDriveFile',
                $id_category,
                $ordering,
                $orderingdir,
                $category->slug,
                $token
            );
            $content           = new stdClass();
            $content->files    = $files;
            $content->category = $category;
        } elseif ($categoryFrom === 'dropbox') {
            $files             = apply_filters(
                'wpfdAddonGetListDropboxFile',
                $id_category,
                $ordering,
                $orderingdir,
                $category->slug,
                $token
            );
            $content           = new stdClass();
            $content->files    = $files;
            $content->category = $category;
        } elseif ($categoryFrom === 'onedrive') {
            $files             = apply_filters(
                'wpfdAddonGetListOneDriveFile',
                $id_category,
                $ordering,
                $orderingdir,
                $category->slug,
                $token
            );
            $content           = new stdClass();
            $content->files    = $files;
            $content->category = $category;
        } else {
            $content           = new stdClass();
            $content->files    = $modelFiles->getFiles($id_category, $ordering, $orderingdir);
            $content->category = $category;
        }
        if ($lstAllFile && !empty($lstAllFile)) {
            $content->files = array_merge($lstAllFile, $content->files);
        }
        // Sort before cut
        $reverse = strtoupper($orderingdir) === 'DESC' ? true : false;
        if ($ordering === 'size') {
            $content->files = wpfd_sort_by_property($content->files, 'size', 'ID', $reverse);
        } elseif ($ordering === 'version') {
            $content->files = wpfd_sort_by_property($content->files, 'versionNumber', 'ID', $reverse);
        } elseif ($ordering === 'hits') {
            $content->files = wpfd_sort_by_property($content->files, 'hits', 'ID', $reverse);
        } elseif ($ordering === 'ext') {
            $content->files = wpfd_sort_by_property($content->files, 'ext', 'ID', $reverse);
        } elseif ($ordering === 'description') {
            $content->files = wpfd_sort_by_property($content->files, 'description', 'ID', $reverse);
        } elseif ($ordering === 'title') {
            if ($reverse) {
                // Descending order
                usort($content->files, function ($a, $b) {
                    // String comparisons using a "natural order" algorithm
                    return strnatcmp($b->post_title, $a->post_title);
                });
            } else {
                // Ascending order
                usort($content->files, function ($a, $b) {
                    // String comparisons using a "natural order" algorithm
                    return strnatcmp($a->post_title, $b->post_title);
                });
            }
        } elseif ($ordering === 'created_time') {
            if ($reverse) {
                usort($content->files, array($this, 'cmpCreatedDesc'));
            } else {
                usort($content->files, array($this, 'cmpCreated'));
            }
        } elseif ($ordering === 'modified_time') {
            if ($reverse) {
                usort($content->files, array($this, 'cmpModifiedDesc'));
            } else {
                usort($content->files, array($this, 'cmpModified'));
            }
        }
        $modelConfig     = Model::getInstance('config');
        $global_settings = $modelConfig->getGlobalConfig();
        $limit           = $global_settings['paginationnunber'];
        $page            = Utilities::getInt('page');

        $total  = ceil(count($content->files) / $limit);
        $page   = (int) $page ? $page : 1;
        $offset = ($page - 1) * $limit;
        if ($offset < 0) {
            $offset = 0;
        }
        if (isset($rootcategory->params['theme']) && $rootcategory->params['theme'] !== 'tree') {
            $content->files = array_slice($content->files, $offset, $limit);
        }
        // Crop file titles
        foreach ($content->files as $i => $file) {
            $content->files[$i]->crop_title = $file->post_title;
            if ($root_category) {
                $content->files[$i]->crop_title = WpfdBase::cropTitle(
                    $rootcategory->params,
                    $rootcategory->params['theme'],
                    $file->post_title
                );
            } else {
                $content->files[$i]->crop_title = WpfdBase::cropTitle(
                    $category->params,
                    $category->params['theme'],
                    $file->post_title
                );
            }
        }

        $content->pagination = wpfd_category_pagination(
            array(
                'base'    => '',
                'format'  => '',
                'current' => max(1, $page),
                'total'   => $total
            )
        );
        echo wp_json_encode($content);
        die();
    }

    /**
     * Get all file referent category
     *
     * @param object $model       Files Model
     * @param array  $listCatRef  List cat ref
     * @param string $ordering    Ordering
     * @param string $orderingdir Ordering direction
     *
     * @return array
     */
    public function getAllFileRef($model, $listCatRef, $ordering, $orderingdir)
    {
        $lstAllFile = array();
        if (is_array($listCatRef) && !empty($listCatRef)) {
            foreach ($listCatRef as $key => $value) {
                if (is_array($value) && !empty($value)) {
                    $lstFile    = $model->getFiles($key, $ordering, $orderingdir, $value);
                    $lstAllFile = array_merge($lstFile, $lstAllFile);
                }
            }
        }

        return $lstAllFile;
    }

    /**
     * Method compare Create date
     *
     * @param object $a First file object
     * @param object $b Second file object
     *
     * @return integer
     */
    private function cmpCreated($a, $b)
    {
        return (strtotime($a->created_time) < strtotime($b->created_time)) ? -1 : 1;
    }

    /**
     * Method compare Create date desc
     *
     * @param object $a First file object
     * @param object $b Second file object
     *
     * @return integer
     */
    private function cmpCreatedDesc($a, $b)
    {
        return (strtotime($a->created_time) > strtotime($b->created_time)) ? -1 : 1;
    }

    /**
     * Method compare Modified date
     *
     * @param object $a First file object
     * @param object $b Second file object
     *
     * @return integer
     */
    private function cmpModified($a, $b)
    {
        return (strtotime($a->modified_time) < strtotime($b->modified_time)) ? -1 : 1;
    }

    /**
     * Method compare Modified date desc
     *
     * @param object $a First file object
     * @param object $b Second file object
     *
     * @return integer
     */
    private function cmpModifiedDesc($a, $b)
    {
        return (strtotime($a->modified_time) > strtotime($b->modified_time)) ? -1 : 1;
    }
}
