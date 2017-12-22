<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Uri;
use Parsedown;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class PagesJsonPlugin
 * @package Grav\Plugin
 */
class PagesJsonPlugin extends Plugin
{
    public static function getSubscribedEvents() {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    public function onPluginsInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            $this->enable([
                'onAdminAfterSave'     => ['deploy', 0],
                'onAdminAfterSaveAs'   => ['deploy', 0],
                'onAdminAfterDelete'   => ['deploy', 0],
                'onAdminAfterAddMedia' => ['deploy', 0],
                'onAdminAfterDelMedia' => ['deploy', 0],
            ]);
            return;
        }
        $this->enable([
                'onPageInitialized' => ['onPageInitialized', 0]
            ]);
    }

    public function deploy()
    {
        $url = 'https://api.netlify.com/build_hooks/5a3d00afdf99530f51afb532';

        $parts = parse_url($url);
        $fp = fsockopen($parts['host'],isset($parts['port'])?$parts['port']:80,$errno, $errstr, 30);
        $out = "POST ".$parts['path']." HTTP/1.1\r\n";
        $out.= "Host: ".$parts['host']."\r\n";
        $out.= "Content-Length: 0"."\r\n";
        $out.= "Connection: Close\r\n\r\n";

        fwrite($fp, $out);
        fclose($fp);
    }

    public function onPageInitialized()
    {
        $parsedown = new Parsedown();
        /**
         * @var \Grav\Common\Page\Page $page
         */
        $page = $this->grav['page'];
        $pageArray = $page->toArray();
        $pageArray['language'] = $page->language();
        $pageArray['template'] = $page->template();
        $pageArray['html'] = $parsedown->text($pageArray['content']);
        $pageArray['slug'] = $page->slug();

        $uri = new Uri();
        $uri = Uri::buildUrl([
            'scheme'    => $uri->scheme(true),
            'host'      => $uri->host(),
            'port'      => $uri->port(),
        ]);

        // Collect child pages
        $collection = $page->collection('content', false);
        $children = [];
        foreach ($collection as $item) {
            $child = $item->toArray();
            $child['language'] = $item->language();
            $child['template'] = $item->template();
            $child['slug'] = $item->slug();
            foreach ($item->media()->toArray() as $name => $media) {
                $child['media'][] = $uri . $media->url();
            }
            $child['html'] = $parsedown->text($child['content']);


            // $item->processMarkdown();
            // $child = $item->toArray();
            // $child['ccontent'] = $child['content'];


            $children[] = $child;
        }
        $pageArray['children'] = $children;
        header("Content-Type: application/json");
        echo json_encode($pageArray);
        exit();
    }
}