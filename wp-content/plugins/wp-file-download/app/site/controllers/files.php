<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

use Joomunited\WPFramework\v1_0_5\Controller;
use Joomunited\WPFramework\v1_0_5\Application;
use Joomunited\WPFramework\v1_0_5\Model;
use Joomunited\WPFramework\v1_0_5\Utilities;

defined('ABSPATH') || die();

/**
 * Class WpfdControllerFiles
 */
class WpfdControllerFiles extends Controller
{
    /**
     * Method to download files in categories
     *
     * @param integer $category_id   Category id
     * @param string  $category_name Category name
     *
     * @return void
     */
    public function download($category_id = null, $category_name = null)
    {
        if ($category_id === null && $category_name === null) {
            $category_id   = Utilities::getInt('wpfd_category_id');
            $category_name = Utilities::getInput('wpfd_cat_name', 'GET', 'string');
        }
        $wpUploadDir = wp_upload_dir('wpfd');
        $upload_dir  = $wpUploadDir['path'];
        if (file_exists($upload_dir)) {
            $data = '<html><body bgcolor="#FFFFFF"></body></html>';
            $file = fopen($upload_dir . 'index.html', 'w');
            fwrite($file, $data);
            fclose($file);
            $data = 'deny from all';
            $file = fopen($upload_dir . '.htaccess', 'w');
            fwrite($file, $data);
            fclose($file);
        }
        $modelf    = $this->getModel('file');
        $listFiles = $this->getAllFiles($category_id);
        if (empty($listFiles) && !$listFiles) {
            wp_die(esc_html__('There is no file found in this category!', 'wpfd'));
        }
        // Caculate zip file name
        $zipName      = $upload_dir . $category_id . '-';
        $allFilesName = '';
        foreach ($listFiles as $file) {
            $file         = $modelf->getFullFile($file->ID);
            $allFilesName .= $file->title;
            $allFilesName .= $file->size;
            if ($file->remote_url) {
                $allFilesName .= $file->name . $file->size . $file->ext . $file->version . $file->modified;
            } else {
                $allFilesName .= filemtime(WpfdBase::getFilesPath($file->catid) . '/' . $file->file);
            }
        }
        $zipName .= md5($allFilesName) . '.zip';


        if (!file_exists($zipName)) {
            // Remove all old files with same category id
            $files = glob($upload_dir . $category_id . '-*.zip');
            if (!empty($files) && count($files) > 0) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if ($ext === 'zip') {
                            unlink($file);
                        }
                    }
                }
            }

            // Start zip new file
            $zipFiles = new ZipArchive();
            $zipFiles->open($zipName, ZipArchive::CREATE);
            if (!empty($listFiles) && count($listFiles) > 0) {
                foreach ($listFiles as $key => $filevl) {
                    $file      = $modelf->getFullFile($filevl->ID);
                    $sysfile   = WpfdBase::getFilesPath($filevl->catid) . '/' . $file->file;
                    $file_name = WpfdHelperFile::santizeFileName($file->title);

                    $count = 0;
                    for ($i = 0; $i < $zipFiles->numFiles; $i++) {
                        if ($zipFiles->getNameIndex($i) === $file_name . '.' . $file->ext) {
                            $count++;
                        }
                    }
                    if ($count > 0) {
                        $file_name = $file_name . '(' . $count . ')';
                    }
                    $zipFiles->addFile($sysfile, $file_name . '.' . $file->ext);
                }
            }
            $zipFiles->close();
        }
        WpfdHelperFile::SendDownload($zipName, $category_name . '.zip', 'zip');
        exit();
    }

    /**
     * Download header file
     *
     * @param string  $filename File name
     * @param integer $size     File size
     *
     * @return void
     */
    public function downloadHeader($filename, $size)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
        header('Content-Disposition: attachment; filename="' . esc_html($filename));
        header('Content-Type:  application/zip');
        header('Content-Description: File Transfer');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        if ((int) $size !== 0) {
            header('Content-Length: ' . $size);
        }
        ob_clean();
        flush();
    }

    /**
     * Get all files in category
     *
     * @param integer $catid Category id
     *
     * @return array|string
     */
    private function getAllFiles($catid)
    {
        $app           = Application::getInstance('Wpfd');
        $path_wpfdbase = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
        $path_wpfdbase .= DIRECTORY_SEPARATOR . 'WpfdBase.php';
        require_once $path_wpfdbase;
        $modelConfig     = Model::getInstance('config');
        $modelCategory = Model::getInstance('category');
        $modelFiles    = Model::getInstance('files');
        $modelTokens  = Model::getInstance('tokens');

        $global_settings = $modelConfig->getGlobalConfig();
        $category      = $modelCategory->getCategory($catid);
        if (empty($category)) {
            return '';
        }

        $params           = $category->params;
        $params['social'] = isset($params['social']) ? $params['social'] : 0;
        if ((int) $category->access === 1) {
            $user  = wp_get_current_user();
            $roles = array();
            foreach ($user->roles as $role) {
                $roles[] = strtolower($role);
            }
            $allows = array_intersect($roles, $category->roles);

            $singleuser = false;

            if (isset($params['canview']) && $params['canview'] === '') {
                $params['canview'] = 0;
            }

            $canview = isset($params['canview']) ? $params['canview'] : 0;

            if ((int) $global_settings['restrictfile'] === 1) {
                $user    = wp_get_current_user();
                $user_id = $user->ID;

                if ($user_id) {
                    if ((int) $canview === $user_id || (int) $canview === 0) {
                        $singleuser = true;
                    } else {
                        $singleuser = false;
                    }
                } else {
                    if ((int) $canview === 0) {
                        $singleuser = true;
                    } else {
                        $singleuser = false;
                    }
                }
            }
            // phpcs:ignore PHPCompatibility.PHP.NewFunctions.is_countableFound -- is_countable() was declared in functions.php
            if ((int) $canview !== 0 && is_countable($category->roles) && !count($category->roles)) {
                if ($singleuser === false) {
                    return '';
                }
            } elseif ((int) $canview !== 0 && is_countable($category->roles) && count($category->roles)) { // phpcs:ignore PHPCompatibility.PHP.NewFunctions.is_countableFound -- is_countable() was declared in functions.php
                if (!(!empty($allows) || ($singleuser === true))) {
                    return '';
                }
            } else {
                if (empty($allows)) {
                    return '';
                }
            }
        }



        $sessionToken = isset($_SESSION['wpfdToken']) ? $_SESSION['wpfdToken'] : null;
        if ($sessionToken === null) {
            $token                 = $modelTokens->createToken();
            $_SESSION['wpfdToken'] = $token;
        } else {
            $tokenId = $modelTokens->tokenExists($sessionToken);
            if ($tokenId) {
                $modelTokens->updateToken($tokenId);
                $token                 = $sessionToken;
                $_SESSION['wpfdToken'] = $token;
            } else {
                $token                 = $modelTokens->createToken();
                $_SESSION['wpfdToken'] = $token;
            }
        }

        $category = $modelCategory->getCategory($catid);
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
        $categoryFrom = apply_filters('wpfdAddonCategoryFrom', $catid);
        if ($categoryFrom === 'googleDrive') {
            $files = array();
        } elseif ($categoryFrom === 'dropbox') {
            $files = array();
        } elseif ($categoryFrom === 'onedrive') {
            $files = array();
        } else {
            $files       = $modelFiles->getFiles($catid, 'created_time', 'asc');
            $description = json_decode($category->description, true);
            $lstAllFile  = null;
            if (!empty($description) && isset($description['refToFile'])) {
                if (isset($description['refToFile'])) {
                    $listCatRef = $description['refToFile'];
                    $lstAllFile = $this->getAllFileRef($modelFiles, $listCatRef, 'created_time', 'asc');
                }
            }
            if ($lstAllFile && !empty($lstAllFile)) {
                $files = array_merge($lstAllFile, $files);
            }
            if (!empty($files) && ((int) $global_settings['restrictfile'] === 1)) {
                $user    = wp_get_current_user();
                $user_id = $user->ID;
                foreach ($files as $key => $file) {
                    $metadata = get_post_meta($file->ID, '_wpfd_file_metadata', true);
                    $canview  = isset($metadata['canview']) ? $metadata['canview'] : 0;
                    $canview  = array_map('intval', explode(',', $canview));
                    if ($user_id) {
                        if (!(in_array($user_id, $canview) || in_array(0, $canview))) {
                            unset($files[$key]);
                        }
                    } else {
                        if (!in_array(0, $canview)) {
                            unset($files[$key]);
                        }
                    }
                }
            }
        }

        return $files;
    }

    /**
     * Get all file referent to categories
     *
     * @param object $model       Model
     * @param array  $listCatRef  List categories
     * @param string $ordering    Ordering
     * @param string $orderingdir Ordering dir
     *
     * @return array
     */
    public function getAllFileRef($model, $listCatRef, $ordering, $orderingdir)
    {
        $lstAllFile = array();
        foreach ($listCatRef as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $lstFile    = $model->getFiles($key, $ordering, $orderingdir, $value);
                $lstAllFile = array_merge($lstFile, $lstAllFile);
            }
        }

        return $lstAllFile;
    }
}
