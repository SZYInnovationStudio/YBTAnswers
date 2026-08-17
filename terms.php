<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

guard_installed();

ob_start();
?>
<div class="page-header">
  <h1 class="page-header__title">使用协议</h1>
  <p class="page-header__desc">最后更新：<?= date('Y 年 m 月') ?></p>
</div>

<div class="card">
  <h2>一、网站性质</h2>
  <p>本网站（<?= e(APP_NAME) ?>）是由 <a href="https://www.szystudio.cn" target="_blank" rel="noopener">SZY创新工作室</a> 运营的公益非盈利项目，旨在为信息学竞赛学习者提供题目查阅与学习参考服务。</p>

  <h2>二、内容来源与版权</h2>
  <p>本站展示的题目内容（题目描述、样例等）版权归原作者及原网站 <a href="<?= e(SOURCE_SITE) ?>" target="_blank" rel="noopener">ybt.ssoier.cn</a> 所有。本站仅做收录与展示，并提供指向原网站的溯源链接。如权利人认为本站内容侵犯了您的权益，请与我们联系处理。</p>

  <h2>三、关于答案</h2>
  <p>本站答案由 AI 自动生成（部分经人工校对），<strong>仅供参考</strong>，可能存在错误或不够优化的实现。请勿将本站代码直接用于任何评测系统的正式提交，由此产生的后果与本站无关。</p>

  <h2>四、使用限制</h2>
  <p>访问本站即表示您同意：</p>
  <p>1. 不利用本站内容进行任何违反法律法规的活动；<br>
     2. 不以自动化手段大量抓取本站内容用于商业用途；<br>
     3. 不尝试攻击、破坏本站的正常运行。</p>

  <h2>五、免责声明</h2>
  <p>本站不对内容的准确性、完整性、时效性作任何保证。因使用本站内容造成的任何直接或间接损失，本站不承担责任。</p>

  <h2>六、协议变更</h2>
  <p>本站保留随时修改本协议的权利，修改后的协议自发布之日起生效。</p>
</div>
<?php
$content = ob_get_clean();

render_layout([
    'pageTitle' => '使用协议',
    'content' => $content,
    'activeNav' => '',
]);
