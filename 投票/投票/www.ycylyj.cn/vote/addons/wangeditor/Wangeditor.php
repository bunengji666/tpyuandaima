<?php

namespace addons\wangeditor;

use think\Addons;

/**
 * wangEditor编辑器插件
 */
class Wangeditor extends Addons
{

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

}
