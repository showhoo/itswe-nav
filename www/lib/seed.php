<?php
// SPDX-License-Identifier: MIT
// lib/seed.php — 游客首页推荐站点种子（中英两套）。
// 同时是 favicon 抓取白名单的域名来源之一：仅站点种子域名 + 卡片引用域名允许服务端代抓。
declare(strict_types=1);

/** 游客推荐面板种子：lang => [分组名 => [[名称, 网址], ...]] */
function bookmark_seed(): array {
    return [
        'en' => [
        'Popular' => [
            ['Google', 'https://www.google.com'],
            ['YouTube', 'https://www.youtube.com'],
            ['Wikipedia', 'https://www.wikipedia.org'],
            ['Reddit', 'https://www.reddit.com'],
            ['Amazon', 'https://www.amazon.com'],
            ['X', 'https://x.com'],
            ['Instagram', 'https://www.instagram.com'],
            ['Facebook', 'https://www.facebook.com'],
            ['ChatGPT', 'https://chatgpt.com'],
            ['Netflix', 'https://www.netflix.com'],
            ['eBay', 'https://www.ebay.com'],
            ['Spotify', 'https://open.spotify.com'],
        ],
        'News' => [
            ['BBC', 'https://www.bbc.com/news'],
            ['CNN', 'https://www.cnn.com'],
            ['The New York Times', 'https://www.nytimes.com'],
            ['The Guardian', 'https://www.theguardian.com'],
            ['Reuters', 'https://www.reuters.com'],
            ['AP News', 'https://apnews.com'],
            ['NPR', 'https://www.npr.org'],
            ['CNBC', 'https://www.cnbc.com'],
            ['The Verge', 'https://www.theverge.com'],
            ['TechCrunch', 'https://techcrunch.com'],
            ['Hacker News', 'https://news.ycombinator.com'],
            ['Ars Technica', 'https://arstechnica.com'],
        ],
        'Dev Tools' => [
            ['GitHub', 'https://github.com'],
            ['Stack Overflow', 'https://stackoverflow.com'],
            ['MDN', 'https://developer.mozilla.org'],
            ['GitLab', 'https://gitlab.com'],
            ['CodePen', 'https://codepen.io'],
            ['npm', 'https://www.npmjs.com'],
            ['W3Schools', 'https://www.w3schools.com'],
            ['freeCodeCamp', 'https://www.freecodecamp.org'],
            ['LeetCode', 'https://leetcode.com'],
            ['Can I Use', 'https://caniuse.com'],
            ['Regex101', 'https://regex101.com'],
            ['DevDocs', 'https://devdocs.io'],
        ],
        'Tools' => [
            ['Gmail', 'https://mail.google.com'],
            ['Google Maps', 'https://maps.google.com'],
            ['Google Translate', 'https://translate.google.com'],
            ['WhatsApp', 'https://web.whatsapp.com'],
            ['Zoom', 'https://zoom.us'],
            ['Canva', 'https://www.canva.com'],
            ['Notion', 'https://www.notion.so'],
            ['Dropbox', 'https://www.dropbox.com'],
            ['Speedtest', 'https://www.speedtest.net'],
            ['Weather', 'https://weather.com'],
            ['AWS', 'https://aws.amazon.com/console/'],
            ['Yahoo', 'https://www.yahoo.com'],
        ],
    ],
        'zh' => [
        '常用网站' => [
            ['百度', 'https://www.baidu.com'],
            ['哔哩哔哩', 'https://www.bilibili.com'],
            ['抖音', 'https://www.douyin.com'],
            ['知乎', 'https://www.zhihu.com'],
            ['微博', 'https://weibo.com'],
            ['小红书', 'https://www.xiaohongshu.com'],
            ['豆瓣', 'https://www.douban.com'],
            ['淘宝', 'https://www.taobao.com'],
            ['京东', 'https://www.jd.com'],
            ['拼多多', 'https://www.pinduoduo.com'],
            ['网易云音乐', 'https://music.163.com'],
            ['爱奇艺', 'https://www.iqiyi.com'],
        ],
        '资讯阅读' => [
            ['腾讯新闻', 'https://news.qq.com'],
            ['网易新闻', 'https://news.163.com'],
            ['新浪新闻', 'https://news.sina.com.cn'],
            ['搜狐新闻', 'https://www.sohu.com'],
            ['凤凰网', 'https://www.ifeng.com'],
            ['今日头条', 'https://www.toutiao.com'],
            ['澎湃新闻', 'https://www.thepaper.cn'],
            ['界面新闻', 'https://www.jiemian.com'],
            ['IT之家', 'https://www.ithome.com'],
            ['虎嗅', 'https://www.huxiu.com'],
            ['36氪', 'https://36kr.com'],
            ['少数派', 'https://sspai.com'],
        ],
        '开发工具' => [
            ['GitHub', 'https://github.com'],
            ['Stack Overflow', 'https://stackoverflow.com'],
            ['MDN', 'https://developer.mozilla.org'],
            ['CSDN', 'https://www.csdn.net'],
            ['稀土掘金', 'https://juejin.cn'],
            ['菜鸟教程', 'https://www.runoob.com'],
        ],
        '实用工具' => [
            ['DeepSeek', 'https://chat.deepseek.com'],
            ['百度翻译', 'https://fanyi.baidu.com'],
            ['腾讯文档', 'https://docs.qq.com'],
            ['百度网盘', 'https://pan.baidu.com'],
            ['高德地图', 'https://www.amap.com'],
            ['12306', 'https://www.12306.cn'],
        ],
    ],
    ];
}

/** 种子站点的全部主机名（小写、去重），供 favicon.php 白名单使用 */
function bookmark_seed_hosts(): array {
    $hosts = [];
    foreach (bookmark_seed() as $sections) {
        foreach ($sections as $sites) {
            foreach ($sites as $site) {
                $h = strtolower((string)parse_url($site[1], PHP_URL_HOST));
                if ($h !== '') $hosts[] = $h;
            }
        }
    }
    return array_values(array_unique($hosts));
}
