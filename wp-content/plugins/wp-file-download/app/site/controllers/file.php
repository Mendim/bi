<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

use Joomunited\WPFramework\v1_0_5\Application;
use Joomunited\WPFramework\v1_0_5\Controller;
use Joomunited\WPFramework\v1_0_5\Utilities;
use Joomunited\WPFramework\v1_0_5\Model;

defined('ABSPATH') || die();

/**
 * Class WpfdControllerFile
 */
class WpfdControllerFile extends Controller
{

    /**
     * Method to download a file
     *
     * @param integer $id      File id
     * @param integer $catid   Category id
     * @param integer $preview Is preview
     *
     * @return void
     */
    public function download($id = 0, $catid = 0, $preview = 0)
    {
        if (empty($catid)) {
            $catid = Utilities::getInput('wpfd_category_id', 'GET', 'none');
        }
        if (empty($id)) {
            $id = Utilities::getInput('wpfd_file_id', 'GET', 'none');
        }
        if (empty($preview)) {
            $preview = Utilities::getInput('preview', 'GET', 'none');
        }
        if (empty($id) || empty($catid)) {
            exit();
        }
        Application::getInstance('Wpfd');
        $modelCategory = Model::getInstance('category');
        $modelConfig   = Model::getInstance('config');
        $model         = $this->getModel();
        $config        = $modelConfig->getGlobalConfig();

        $category     = $modelCategory->getCategory($catid);
        $modelNotify  = $this->getModel('notification');
        $configNotify = $modelNotify->getNotificationsConfig();


        if (empty($category) || is_wp_error($category)) {
            exit(esc_html__('Category is not correct', 'wpfd'));
        }


        /**
         * Filter to check category source
         *
         * @param integer Term id
         *
         * @return string
         *
         * @internal
         */
        $categoryFrom = apply_filters('wpfdAddonCategoryFrom', $catid);
        if ($categoryFrom === 'googleDrive') {
            /**
             * Filter to check google category
             *
             * @param integer Term id
             * @param string  File id
             *
             * @internal
             *
             * @return string
             */
            $catid = apply_filters('wpfdAddonDownloadCheckGoogleDriveCategory', $catid, $id);
            if (empty($catid)) {
                exit(esc_html__('Download url is not correct', 'wpfd'));
            }
        } elseif ($categoryFrom === 'dropbox') {
            /**
             * Filter to check dropbox category
             *
             * @param integer Term id
             * @param string  File id
             *
             * @internal
             *
             * @return string
             */
            $catid = apply_filters('wpfdAddonDownloadCheckDropboxCategory', $catid, $id);
            if (empty($catid)) {
                exit(esc_html__('Download url is not correct', 'wpfd'));
            }
        } elseif ($categoryFrom === 'onedrive') {
            /**
             * Filter to check onedrive category
             *
             * @param integer Term id
             * @param string  File id
             *
             * @internal
             *
             * @return string
             */
            $catid = apply_filters('wpfdAddonDownloadCheckOneDriveCategory', $catid, $id);
            if (empty($catid)) {
                exit(esc_html__('Download url is not correct', 'wpfd'));
            }
        } else {
            $file_catid = $model->getFileCategory($id);
            if ((int) $catid !== (int) $file_catid) {
                exit(esc_html__('Download url is not correct', 'wpfd'));
            }
        }

        if ((int) $category->access === 1) {
            $user  = wp_get_current_user();
            $roles = array();
            foreach ($user->roles as $role) {
                $roles[] = strtolower($role);
            }
            $allows = array_intersect($roles, $category->roles);

            if (empty($allows)) {
                $token       = Utilities::getInput('token', 'GET', 'string');
                Application::getInstance('Wpfd');
                $modelTokens = Model::getInstance('tokens');
                $modelTokens->removeTokens();
                $tokenId = $modelTokens->tokenExists($token);
                if ($tokenId) {
                    $modelTokens->updateToken($tokenId);
                } else {
                    if (isset($category->params['canview']) && !empty($category->params['canview'])) {
                        if ((int) $category->params['canview'] !== 0 && (int) $category->params['canview'] !== $user->ID) {
                            exit(esc_html__("You don't have permission", 'wpfd'));
                        }
                    } else {
                        exit(esc_html__('Not authorized', 'wpfd'));
                    }
                }
            }
        }

        /**
         * Download file from WP FileDownload when not exist $fileInfo or wpfdAddon not active
         */
        if ($categoryFrom === 'googleDrive') {
            /**
             * Filters to get google file info
             *
             * @param string File id
             *
             * @internal
             *
             * @return object
             */
            $file = apply_filters('wpfdAddonDownloadGoogleDriveFile', $id);
            if ((int) $preview === 1) {
                $contenType = WpfdHelperFile::mimeType(strtolower($file->ext));
            } else {
                if (strtolower($file->ext) === 'pdf' && (int) $config['open_pdf_in'] === 1) {
                    $contenType = WpfdHelperFile::mimeType(strtolower($file->ext));
                } else {
                    $contenType = 'application/octet-stream';
                }
            }

            /**
             * Action fire right before a file download.
             * Do not echo anything here or file download will corrupt
             *
             * @param object  File id
             * @param array   Source
             */
            do_action('wpfd_file_download', $id, array('source' => 'googledrive'));

            if ($file->size <= 5*1024*1024) {
                $filedownload = $file->title . '.' . $file->ext;
                $this->downloadHeader($filedownload, (int) $file->size, $contenType, $config, $file, $preview);
                $googleCate = new wpfdAddonGoogleDrive;
                $googleCate->downloadSmallFile($file);
            } else {
                $googleCate = new wpfdAddonGoogleDrive;
                $googleCate->downloadLargeFile($file, $contenType);
            }
            $this->sendEmail(null, $category->params['category_own'], $configNotify, $category->name, $file->title);
        } elseif ($categoryFrom === 'dropbox') {
            /**
             * Filters to get dropbox file info
             *
             * @param string File id
             *
             * @internal
             *
             * @return object
             */
            list($file, $fMeta) = apply_filters('wpfdAddonDownloadDropboxFile', $id);
            $ext = strtolower(pathinfo($fMeta['path_display'], PATHINFO_EXTENSION));
            setlocale(LC_ALL, 'en_US.UTF-8');
            $title = pathinfo($fMeta['path_display'], PATHINFO_FILENAME);

            if ((int) $preview === 1) {
                $contenType = WpfdHelperFile::mimeType(strtolower($ext));
            } else {
                if (strtolower($ext) === 'pdf' && (int) $config['open_pdf_in'] === 1) {
                    $contenType = WpfdHelperFile::mimeType(strtolower($ext));
                } else {
                    $contenType = 'application/octet-stream';
                }
            }

            //incr hits
            $fileInfos = WpfdAddonHelper::getDropboxFileInfos();
            if (!empty($fileInfos)) {
                $hits                           = $fileInfos[$catid][$id]['hits'] + 1;
                $fileInfos[$catid][$id]['hits'] = $hits;
            } else {
                $fileInfos[$catid][$id]['hits'] = 1;
            }
            WpfdAddonHelper::setDropboxFileInfos($fileInfos);

            $fileObj        = new stdClass();
            $fileObj->ext   = $ext;
            $fileObj->title = $title;
            $this->sendEmail('', $category->params['category_own'], $configNotify, $category->name, $fileObj->title);

            /**
             * Action fire right before a Dropbox file download.
             * Do not echo anything here or file download will corrupt
             *
             * @param object  File id
             * @param array   Source
             *
             * @ignore Hook already documented
             */
            do_action('wpfd_file_download', $id, array('source' => 'dropbox'));

            $this->downloadHeader(
                $fileObj->title . '.' . $ext,
                (int) filesize($file),
                $contenType,
                $config,
                $fileObj,
                $preview
            );
            readfile($file);
            unlink($file);
        } elseif ($categoryFrom === 'onedrive') {
            /**
             * Filters to get onedrive file info
             *
             * @param string File id
             *
             * @internal
             *
             * @return object
             */
            $file = apply_filters('wpfdAddonDownloadOneDriveFile', $id);
            if ((int) $preview === 1) {
                $contenType = WpfdHelperFile::mimeType(strtolower($file->ext));
            } else {
                if (strtolower($file->ext) === 'pdf' && (int) $config['open_pdf_in'] === 1) {
                    $contenType = WpfdHelperFile::mimeType(strtolower($file->ext));
                } else {
                    $contenType = 'application/octet-stream';
                }
            }
            $filedownload = $file->title . '.' . $file->ext;

            /**
             * Action fire right before a Onedrive file download.
             * Do not echo anything here or file download will corrupt
             *
             * @param object  File id
             * @param array   Source
             *
             * @ignore Hook already documented
             */
            do_action('wpfd_file_download', $id, array('source' => 'onedrive'));

            $this->sendEmail(null, $category->params['category_own'], $configNotify, $category->name, $file->title);
            $this->downloadHeader($filedownload, (int) $file->size, $contenType, $config, $file, $preview);
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- file content output
            echo $file->datas;
        } else {
            $file      = $model->getFullFile($id);
            $file_meta = get_post_meta($id, '_wpfd_file_metadata', true);

            $remote_url = isset($file_meta['remote_url']) ? $file_meta['remote_url'] : false;

            $model->hit($id);
            $model->addCountChart($id);

            //todo : verifier les droits d'acces à la catéorgie du fichier
            if (!empty($file) && $file->ID) {
                $filename = WpfdHelperFile::santizeFileName($file->title);
                if ($filename === '') {
                    $filename = 'download';
                }
                if ($remote_url) {
                    $url = $file_meta['file'];
                    header('Location: ' . $url);
                } else {
                    $preview = Utilities::getInput('preview', 'GET', 'none');
                }

                $sysfile = WpfdBase::getFilesPath($file->catid) . '/' . $file->file;
                if (file_exists($sysfile)) {
                    $filedownload = $filename . '.' . $file->ext;
                    /**
                     * Action fire right before a file download.
                     * Do not echo anything here or file download will corrupt
                     *
                     * @param object  File id
                     * @param array   Source
                     *
                     * @ignore Hook already documented
                     */
                    do_action('wpfd_file_download', $id, array('source' => 'local'));
                    WpfdHelperFile::sendDownload(
                        $sysfile,
                        $filedownload,
                        $file->ext,
                        ((int) $preview === 1) ? true : false,
                        ((int) $config['open_pdf_in'] === 1) ? true : false
                    );
                    $this->sendEmail(
                        $file->author,
                        $category->params['category_own'],
                        $configNotify,
                        $category->name,
                        $file->title
                    );
                } else {
                    exit(esc_html__('File not found', 'wpfd'));
                }
            }
        }
        exit();
    }

    /**
     * Download header file
     *
     * @param string  $filename   File name
     * @param integer $size       Size
     * @param string  $contenType Content type
     * @param array   $config     Config
     * @param object  $ob         File object
     * @param integer $preview    Preview
     *
     * @return void
     */
    public function downloadHeader($filename, $size, $contenType, $config, $ob, $preview)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
        if ((int) $config['open_pdf_in'] === 1 && strtolower($ob->ext) === 'pdf' && (int) $preview === 1) {
            header('Content-Disposition: inline; filename="' . esc_html($filename) . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . esc_html($filename) . '"');
        }
        header('Content-Type: ' . esc_attr($contenType));
        header('Content-Description: File Transfer');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        if ($size !== 0) {
            header('Content-Length: ' . $size);
        }
        ob_clean();
        flush();
    }

    /**
     * Method send email notification
     *
     * @param integer $user_id      User id
     * @param integer $cat_userid   Category owner user id
     * @param array   $configNotifi Config
     * @param string  $cat_name     Category name
     * @param string  $file_title   File title
     *
     * @return void
     */
    public function sendEmail($user_id, $cat_userid, $configNotifi, $cat_name, $file_title)
    {
        $send_mail_active = array();
        $cat_user_id[]    = $cat_userid;
        $list_superAdmin  = WpfdHelperFiles::getListIDSuperAdmin();
        if ((int) $configNotifi['notify_file_owner'] === 1 && $user_id !== null) {
            $user = get_userdata($user_id)->data;
            array_push($send_mail_active, $user->user_email);
            WpfdHelperFiles::sendMail('download', $user, $cat_name, get_site_url(), $file_title);
        }
        if ((int) $configNotifi['notify_category_owner'] === 1) {
            foreach ($cat_user_id as $item) {
                $user = get_userdata($item)->data;
                if (!in_array($user->user_email, $send_mail_active)) {
                    array_push($send_mail_active, $user->user_email);
                    WpfdHelperFiles::sendMail('download', $user, $cat_name, get_site_url(), $file_title);
                }
            }
        }
        if ($configNotifi['notify_add_event_email'] !== '') {
            $emails = explode(',', $configNotifi['notify_add_event_email']);
            foreach ($emails as $item) {
                $obj_user               = new stdClass;
                $obj_user->display_name = '';
                $obj_user->user_email   = $item;
                if (!in_array($item, $send_mail_active)) {
                    array_push($send_mail_active, $item);
                    WpfdHelperFiles::sendMail('download', $obj_user, $cat_name, get_site_url(), $file_title);
                }
            }
        }
        if ((int) $configNotifi['notify_super_admin'] === 1) {
            foreach ($list_superAdmin as $items) {
                $user = get_userdata($items)->data;
                if (!in_array($user->user_email, $send_mail_active)) {
                    array_push($send_mail_active, $user->user_email);
                    WpfdHelperFiles::sendMail('download', $user, $cat_name, get_site_url(), $file_title);
                }
            }
        }
    }
}
