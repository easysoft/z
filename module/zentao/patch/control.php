<?php
/**
 * The control file of index module of ZenTaoPHP.
 *
 * The author disclaims copyright to this source code.  In place of
 * a legal notice, here is a blessing:
 *
 *  May you do good and not evil.
 *  May you find forgiveness for yourself and forgive others.
 *  May you share freely, never taking more than you give.
 */
class patch extends control
{
    /**
     * The index page.
     *
     * @param  array $params
     * @access public
     * @return void
     */
    public function entry($params)
    {
        if(empty($params)) return $this->printHelp();

        foreach($params as $key => $param)
        {
            if(method_exists($this, $key)) return $this->$key(array('patchID' => $param));
            return $this->printHelp();
        }
    }

    public function printHelp($type = 'patch')
    {
        return fwrite(STDOUT, $this->lang->patch->help->$type);
    }

    /**
     * The list page.
     *
     * @param  array $params
     * @access public
     * @return void
     */
    public function list($params)
    {
        global $argc;
        if(isset($params['help']) or ($argc > 3 && empty($params))) return $this->printHelp('list');

        $patchList = array();
        for($i = 1; $i < 10; $i++)
        {
            $patch = array();
            $patch['type']      = 'bug' . $i;
            $patch['code']      = 'patch00' . $i;
            $patch['title']     = mb_substr('这个包处理了任务相关的bug', 15 - $i) . $i;
            $patch['date']      = '2022-01-0' . $i;
            $patch['installed'] = $this->lang->no;
            $patchList[] = $patch;
        }

        if(isset($params['showAll']))
        {
            $patchList[] = array(
                'type'      => 'story',
                'code'      => 'story',
                'title'     => '这个是需求标题',
                'date'      => '2022-05-01',
                'installed' => $this->lang->yes
            );
        }
        elseif(isset($params['local']))
        {
            $patchList = array();
            $patchList[] = array(
                'type'      => 'story',
                'code'      => 'story',
                'title'     => '这个是需求标题',
                'date'      => '2022-05-01',
                'installed' => $this->lang->yes
            );
        }

        return $this->printList($patchList, array('type', 'code', 'title', 'date', 'installed'), $this->lang->patch);
    }

    /**
     * The view page.
     *
     * @param  array $params
     * @access public
     * @return void
     */
    public function view($params)
    {
        if(empty($params) or !isset($params['patchID']) or empty($params['patchID']) or isset($params['help'])) return $this->printHelp('view');

        $patchID = $params['patchID'];

        $title = '这是一个标题';
        $desc  = '这是描述信息';
        $files = "a.php, test/b.php";
        $logs  = "改了几个已知的bug，调整了用户交互";

        return fwrite(STDOUT, sprintf($this->lang->patch->viewPage, $patchID, $title, $desc, $files, $logs));
    }

    /**
     * Install patch.
     *
     * @param  array  $params
     * @access public
     * @return void
     */
    public function install($params)
    {
        if(empty($params) or !isset($params['patchID']) or empty($params['patchID']) or isset($params['help'])) return $this->printHelp('install');
        if(!isset($this->config->zt_webDir) or empty($this->config->zt_webDir)) return fwrite(STDERR, $this->lang->patch->error->runSet);

        /* Check whether the parameter is an ID or a path. */
        if(strpos($params['patchID'], '.zip') !== false)
        {
            $patchPath = realpath($params['patchID']);
            if(!$patchPath) $patchPath = realpath($this->config->runDir . DS . $params['patchID']);
            if(!$patchPath) return fwrite(STDERR, sprintf($this->lang->patch->error->invalidName, $params['patchID']));

            /* Verification name format. */
            $pathList    = explode(DS, $patchPath);
            $packageKey  = count($pathList) - 1;
            $packageName = $pathList[$packageKey];
            if(!$this->patch->checkPatchName($packageName)) return fwrite(STDERR, sprintf($this->lang->patch->error->invalidName, $params['patchID']));

            $saveDir = $this->config->zt_webDir . DS . 'tmp' . DS . 'patch' . DS . $packageName . DS;
        }
        else
        {
            $saveDir = $this->config->zt_webDir . DS . 'tmp' . DS . 'patch' . DS . $params['patchID'] . DS;
        }

        /* Check whether installed. */
        if(file_exists($saveDir . 'install.lock')) return fwrite(STDERR, $this->lang->patch->error->installed);
        if($params['patchID'] == 'none') return fwrite(STDERR, $this->lang->patch->error->invalid);
        if($params['patchID'] == 'incompatible') return fwrite(STDERR, $this->lang->patch->error->incompatible);

        if(!file_exists($saveDir) && !mkdir($saveDir, 0777, true)) return fwrite(STDERR, sprintf($this->lang->patch->error->notWritable, $saveDir));

        $backupPath = $saveDir . 'backup.zip';
        if(!isset($patchPath))
        {
            $patchPath = $saveDir . 'patch.zip';

            if(!file_exists($patchPath))
            {
                fwrite(STDOUT, $this->lang->patch->downloading);
                $url  = 'http://cyy.oop.cc/data/upload/config.zip';
                if(!@copy($url, $patchPath)) return fwrite(STDERR, error_get_last() . PHP_EOL);
                fwrite(STDOUT, $this->lang->patch->down);
            }
        }

        $this->app->loadClass('pclzip', true);
        $zip   = new pclzip($patchPath);
        $files = $zip->listContent();
        if($files === 0) return fwrite(STDERR, $zip->errorInfo() . PHP_EOL);

        fwrite(STDOUT, $this->lang->patch->backuping);
        $fileNames = array();
        foreach($files as $file) $fileNames[] = $this->config->zt_webDir . DS . $file['filename'];

        $zip->changeFile($backupPath);
        if($zip->create($fileNames, PCLZIP_OPT_REMOVE_PATH, $this->config->zt_webDir) === 0) return fwrite(STDERR,  $zip->errorInfo() . PHP_EOL);
        fwrite(STDOUT, $this->lang->patch->down);

        fwrite(STDOUT, $this->lang->patch->installing);
        $zip->changeFile($patchPath);
        if($zip->extract(PCLZIP_OPT_PATH, $this->config->zt_webDir) === 0) return fwrite(STDERR, $zip->errorInfo() . PHP_EOL);
        @touch($saveDir . 'install.lock');
        fwrite(STDOUT, $this->lang->patch->installDone);
    }

    /**
     * Revert patch.
     *
     * @param  array  $params
     * @access public
     * @return void
     */
    public function revert($params)
    {
        if(empty($params) or !isset($params['patchID']) or empty($params['patchID']) or isset($params['help'])) return $this->printHelp('revert');

        if(!isset($this->config->zt_webDir) or empty($this->config->zt_webDir)) return fwrite(STDERR, $this->lang->patch->error->runSet);

        $saveDir = $this->config->zt_webDir . DS . 'tmp' . DS . 'patch' . DS . $params['patchID'] . DS;
        if(!file_exists($saveDir . 'install.lock')) return fwrite(STDERR, $this->lang->patch->error->notInstall);

        $backupPath = $saveDir . 'backup.zip';

        $this->app->loadClass('pclzip', true);
        $zip = new pclzip($backupPath);

        fwrite(STDOUT, $this->lang->patch->restoring);
        if($zip->extract(PCLZIP_OPT_PATH, '/') === 0) return fwrite(STDERR, $zip->errorInfo() . PHP_EOL);
        @unlink($saveDir . 'install.lock');
        fwrite(STDOUT, $this->lang->patch->restored);
    }

    /**
     * Build patch file.
     *
     * @param  array  $params
     * @access public
     * @return void
     */
    public function build($params)
    {
        if(isset($params['help'])) return $this->printHelp('build');

        $buildInfo = new stdClass();

        /* Check versions. */
        fwrite(STDOUT, $this->lang->patch->build->versionTip);
        while(true)
        {
            $versions = $this->readInput();
            if(!empty($versions) and $this->patch->checkVersion($versions))
            {
                $buildInfo->version = $versions;
                break;
            }

            fwrite(STDERR, sprintf($this->lang->patch->error->build->version, $versions));
        }

        /* Check type. */
        fwrite(STDOUT, $this->lang->patch->build->typeTip);
        while(true)
        {
            $type = $this->readInput();

            if(in_array($type, array('bug', 'story')))
            {
                $buildInfo->type = $type;
                break;
            }

            fwrite(STDERR, sprintf($this->lang->patch->error->build->type, $type));
        }

        /* Check id. */
        fwrite(STDOUT, $this->lang->patch->build->idTip);
        while(true)
        {
            $ID = $this->readInput();

            if(!empty((int)$ID))
            {
                $buildInfo->patchName = array();

                $versions = explode(',', $buildInfo->version);
                foreach($versions as $version)
                {
                    $patchName = sprintf($this->config->patch->nameTpl, trim($version), $buildInfo->type, (int)$ID);
                    if($patchName == 'zentao.16.5.bug.1234.zip')
                    {
                        fwrite(STDERR, sprintf($this->lang->patch->error->build->patch, $patchName));
                        break;
                    }
                    $buildInfo->patchName[] = $patchName;
                }

                $buildInfo->id = $ID;
                break;
            }

            fwrite(STDERR, sprintf($this->lang->patch->error->build->id, $ID));
        }

        /* Check path. */
        fwrite(STDOUT, $this->lang->patch->build->pathTip);
        while(true)
        {
            $path = $this->readInput();

            if(!empty($path) and !file_exists($path)) $path = realpath($this->config->runDir . DS .$path);

            if(!empty($path) and file_exists($path))
            {
                $buildInfo->path = $path;
                break;
            }

            fwrite(STDERR, sprintf($this->lang->patch->error->build->path, $path));
        }

        /* Zip create. */
        fwrite(STDOUT, $this->lang->patch->building . PHP_EOL);
        $this->app->loadClass('pclzip', true);
        foreach($buildInfo->patchName as $patch)
        {
            $savePath = $this->config->runDir . DS . $patch;

            $zip = new pclzip($savePath);
            if($zip->create($buildInfo->path, PCLZIP_OPT_REMOVE_PATH, $buildInfo->path) === 0) return fwrite(STDERR, $zip->errorInfo() . PHP_EOL);
        }

        return fwrite(STDOUT, $this->lang->patch->buildSuccess . PHP_EOL);
    }
}
