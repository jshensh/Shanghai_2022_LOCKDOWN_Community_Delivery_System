<?php
namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

use app\common\service\TencentSms;
use app\api\model\v1\Config as ConfigModel;

class Test extends Command
{
    protected function configure()
    {
        $this->setName('Test')
        ->setDescription('Test');
    }

    protected function execute(Input $input, Output $output)
    {
        $sms = TencentSms::init(
            ConfigModel::where('k', 'sms_secret')->value('v'),
            ConfigModel::where('k', 'sms_key')->value('v'),
            ConfigModel::where('k', 'sms_appid')->value('v'),
            "403阿姨的试验田公众号"
        );

        $receiver = [
            '13005397150' => '王志勇',
            '13011260511' => '陈',
            '13021298993' => '主先生',
            '13022033809' => '张田所',
            '13024295725' => '伊藤诚',
            '13026007957' => '徐军',
            '13028954995' => 'TYFNN',
            '13030106636' => '成先生',
            '13032032176' => '王林',
            '13033324868' => 'Kirito',
            '13033655082' => '某某叶',
            '13036151839' => '王竞立',
            '13036952694' => '小石',
            '13040788159' => '顾先生',
            '13058820045' => 'Lao_Liu',
            '13059943389' => '刘先生',
            '13060223484' => '曹越',
            '13060571216' => '张龙标',
            '13061480527' => '侯乐',
            '13064544338' => '羊驼',
            '13065080238' => '刘德威',
            '13065535658' => '邵先生',
            '13073886804' => '何先生',
            '13074668493' => '腾达',
            '13086653589' => '吴南海',
            '13088688664' => '果冻',
            '13094639986' => '严',
            '13095657994' => '何强',
            '13097346425' => '买家',
            '13098887182' => '郝锋',
            '13100872598' => '闻卓',
            '13101250398' => '冯仁首',
            '13109696136' => '皮皮',
            '13113142735' => '袁先生',
            '13120588804' => '(欧)189929',
            '13123210089' => '陈',
            '13148413316' => '林木木',
            '13152656301' => '赵',
            '13153909951' => '依露娜',
            '13156187245' => '言生',
            '13168771209' => '文俊超',
            '13172808475' => '陈嘉彬',
            '13175330334' => '张三',
            '13195323699' => '王鸣',
            '13196199945' => '曾浩洋',
            '13209257111' => '冯诗哲',
            '13210970756' => '洛佳',
            '13215551862' => '雷店成',
            '13215976282' => '施华斌',
            '13217483413' => '唐岩峻',
            '13232500930' => '二十',
            '13247829331' => '刘宇航',
            '13248408202' => '孙余',
            '13249990588' => '邓先生',
            '13250093587' => '谢先生',
            '13252088879' => '化名长一点就不会撞名',
            '13256143369' => '任先生',
            '13260696550' => '贺进年',
            '13263818643' => 'CBT',
            '13268702042' => '李大爷',
            '13288622137' => 'Theergold',
            '13291426196' => '陈志炜',
            '13296550631' => '蒋一欣',
            '13302960519' => '陳珂磊(K2621)',
            '13309686967' => '贾傲',
            '13335717384' => '阿卜',
            '13336400128' => '曹乐凡',
            '13349890800' => '董林',
            '13354623963' => '薛文龙',
            '13358232333' => 'Fangfei yang',
            '13361483905' => '叶重',
            '13369641980' => '樊克',
            '13382053700' => 'Jeremy.',
            '13386100386' => '姜枫',
            '13391178810' => '叶文平',
            '13392615418' => '李明',
            '13392802983' => '德先生',
            '13397267211' => '戴工',
            '13403333457' => '闫先生',
            '13406203653' => '刘洪武',
            '13408271577' => '周生生',
            '13417225284' => '郑仲康',
            '13423880555' => '王肖',
            '13433648767' => '李照寒',
            '13436895609' => '郑',
            '13437751026' => '叶生',
            '13502481603' => '李田所',
            '13519238924' => '张玉乾',
            '13527811161' => '卢锐生',
            '13533816563' => '胡生',
            '13547875975' => '田纳西',
            '13562600882' => '贾新刚',
            '13562681757' => '韩林',
            '13585633069' => '阚旋',
            '13586058719' => '何承远',
            '13591229163' => '金毛',
            '13599426078' => '孙先生',
            '13612226032' => '赖俊良',
            '13615853337' => '孙s',
            '13622539707' => '邓兆飞',
            '13626824654' => '车晓展',
            '13632282944' => '熊家乐',
            '13632457492' => '张生',
            '13636054941' => '罗云千',
            '13656411037' => '纪鹏',
            '13657911867' => '刘泽昊',
            '13660284526' => '吴路平',
            '13661307275' => '高渐离',
            '13665239753' => '陈某',
            '13666726650' => '沈伟',
            '13681856317' => '姚震宇',
            '13703012630' => '随心',
            '13706840694' => '周汀',
            '13732195764' => '李欢欢',
            '13763599901' => 'yuu',
            '13764087613' => '耿磊',
            '13788646493' => '林观钊',
            '13798293793' => '李冬冬',
            '13809668580' => '黎海涛',
            '13810843915' => 'dram',
            '13819765281' => '谢阿会',
            '13827211501' => '辛玖',
            '13829032419' => '陈俊',
            '13881103041' => '羊羊',
            '13915947931' => '管莎',
            '13921201383' => '朱春华',
            '13940221095' => '李田所',
            '13960822581' => '叶多',
            '13963515572' => '张国辉',
            '13971772379' => '吴',
            '13972720186' => '蔡鲸',
            '13986106039' => '程冲',
            '13986527267' => '陈廷益',
            '13996083372' => '哲学家',
            '14723856078' => '李田所',
            '15021062859' => '蔡先生',
            '15023086911' => '罗',
            '15031605353' => '刘彪',
            '15033578982' => '柳烨浩',
            '15038056718' => '刘先生',
            '15038232635' => '王瑞鹏',
            '15055104119' => '张继强',
            '15055311230' => '施先生',
            '15057896043' => '舍',
            '15060330669' => '彼得',
            '15070787978' => '赖堃',
            '15077923342' => '古宜民',
            '15112282157' => '何正',
            '15138686838' => '张玉琦',
            '15166444545' => '孟孙祥',
            '15173099935' => '黄先生',
            '15182706383' => '何浩睿',
            '15189800236' => '关羽',
            '15195860328' => '刘昌杰',
            '15212068155' => '丁丰恺',
            '15215014369' => '王俊飞',
            '15225911306' => '苏长江',
            '15241399611' => '夏先生',
            '15245548609' => 'Xiaoxi654',
            '15246110405' => 'KKK',
            '15256852573' => '孙怀强',
            '15283832121' => '黄渝程',
            '15305851125' => '魏金峰',
            '15310487288' => '杨同学',
            '15327116372' => '龚维维',
            '15335170613' => '周晓',
            '15362736628' => '郑天莹',
            '15362942726' => '林坤',
            '15382290673' => 'SuperMarioSF',
            '15389721757' => '冉冉',
            '15395740292' => '王小明',
            '15505517535' => '周浩',
            '15508358813' => '图森破',
            '15521425524' => '赖俊晖',
            '15523531307' => '张',
            '15533543115' => '任家鲁',
            '15553212985' => '朱震',
            '15589832395' => '刘江',
            '15615386668' => '张先生',
            '15618713503' => '狼先森',
            '15619206956' => '任心怡',
            '15620237707' => '杨先生',
            '15620910328' => '刘剑峰',
            '15622206293' => '郭',
            '15622255826' => '杨先生',
            '15623527550' => '柏',
            '15626228642' => '苏伊特',
            '15626238225' => '李加',
            '15628911453' => '于杰',
            '15642788588' => '孙二木',
            '15658127863' => '逆时.',
            '15659750053' => '快递柜',
            '15664451126' => '刘凯',
            '15665393952' => '王丛天',
            '15669028644' => 'ncccc',
            '15678525664' => '廉贞',
            '15678617653' => '周正',
            '15701365340' => '赵云',
            '15716121736' => '郭鹏',
            '15722107976' => '肖振康',
            '15725267592' => '尹志瀚',
            '15726817039' => '吴凯',
            '15731909186' => '郭文谦',
            '15735513142' => '豆豆',
            '15756448964' => '张三丰',
            '15757836275' => 'passdejiang',
            '15768909339' => 'WGQ',
            '15800919862' => '李田所',
            '15801882078' => '蔡盛梁',
            '15804058286' => '常先生',
            '15811183280' => '徐兴灿',
            '15811889761' => '王凯',
            '15812597125' => '魏立明',
            '15812734554' => '梁先森',
            '15816949186' => '周文龙',
            '15818497673' => '莫生',
            '15825610638' => '程建锋',
            '15826017027' => '洪',
            '15843429129' => '须弥玉米糊',
            '15850658660' => '周先生',
            '15863443969' => '嘟',
            '15866415958' => '公琳琳',
            '15868829287' => '颖',
            '15871469907' => 'Chochs',
            '15899806086' => '金先生',
            '15910346263' => '刘智',
            '15915767792' => '黄小鸭',
            '15920107843' => '梁永祥',
            '15935136817' => '祝哓明',
            '15957112077' => '陈阿卡',
            '15970405833' => '淘宝5833',
            '15970435631' => '涂哈哈',
            '15998967263' => '饶东',
            '16605561927' => '王大锤',
            '16606648828' => '杨天文',
            '16637105552' => '小刚',
            '16637257001' => '高渐离',
            '16637283583' => '崔长晶',
            '16638126640' => '宗',
            '17079428219' => '阿楠',
            '17080344519' => '刘晓涵',
            '17088354053' => '耿',
            '17090070734' => '海豚',
            '17151458248' => '田先生',
            '17166453648' => '周燕',
            '17168221530' => '申必任',
            '17183461895' => '李华',
            '17191088364' => '潘振宇',
            '17269607650' => '高天宇',
            '17302212591' => '杨世康',
            '17323112763' => '景萍',
            '17323796896' => '彭建',
            '17328687665' => '王险胜',
            '17343004054' => '蒸蒸',
            '17350023107' => '火火火',
            '17364554663' => 'CC',
            '17371446970' => '马瑞丰',
            '17515181878' => '于忠坤',
            '17520503869' => '张先生',
            '17552306882' => '黄彪',
            '17562060590' => '刘书坤',
            '17600598099' => 'rsf',
            '17602188351' => '乔永昌',
            '17602880301' => '陆先生',
            '17602895630' => 'MinZai',
            '17603096154' => '张飞',
            '17605487848' => '李先生',
            '17607157715' => '陈陈陈',
            '17608736736' => '刘俊杰',
            '17610773675' => '严启宇',
            '17611075488' => '陆景',
            '17615128810' => '高先生',
            '17620044039' => '叶',
            '17620754099' => '曹小皮',
            '17621189537' => '范宇杰',
            '17621625601' => '胡先生',
            '17621766520' => 'summer晓',
            '17625283539' => '邓鸿刚',
            '17626046899' => '吴先生',
            '17628737695' => '徐先生',
            '17633560159' => '李申奥',
            '17634407880' => '何建生',
            '17660102361' => '孙先生',
            '17665306236' => '寄',
            '17673910131' => '雷秋',
            '17679337275' => '孟远扬',
            '17679391609' => '陈哲畅',
            '17682348232' => '傅祥文',
            '17692556131' => '郑景坤',
            '17702523901' => '刘田雨',
            '17710880510' => '董',
            '17720952025' => '吴湘',
            '17724498828' => '阿宿',
            '17730425826' => '周启航',
            '17744563473' => '刘毅君',
            '17754429701' => '梁崇浩',
            '17755160183' => '佘能斌',
            '17768011900' => '凌莞',
            '17771627883' => '阮浩宇',
            '17772351776' => '王茂林',
            '17772834556' => '王先生',
            '17778009856' => '马先生',
            '17778021053' => '孙先生',
            '17781329639' => '赵健岚',
            '17786492075' => '刘波涛',
            '17788513294' => '徐旭',
            '17801029263' => '立音喵RK',
            '17875305762' => '小方',
            '17895747636' => '李先生',
            '17895922871' => '連璟怀',
            '17896442844' => '刘生',
            '17898051063' => '张森',
            '17898183670' => '宋岳庭',
            '18001905332' => '周正昌',
            '18006701357' => '董先生',
            '18011907993' => '臭臭',
            '18012797086' => '顾嘉琪',
            '18013614369' => '谢俊',
            '18015387232' => '凹凸曼',
            '18017923579' => '金生',
            '18018506643' => '陈志康',
            '18019008841' => '谢竞辉',
            '18019948278' => '李乾乾',
            '18023236692' => '黄晶辉',
            '18025827319' => '谢先生',
            '18038033277' => '翁继源',
            '18038276970' => '曾泽锋-(WE31723)',
            '18053996887' => '天猫',
            '18056111879' => '汪梓權',
            '18057954085' => '陈志斌',
            '18061998372' => '金诚',
            '18066712511' => '修复活',
            '18066713933' => 'fredriration',
            '18071107722' => '黄腾达',
            '18073319870' => '谭先生',
            '18078607584' => '九0三',
            '18084861659' => '夏雨',
            '18090552580' => '杜先生',
            '18092119385' => '牛晨旭',
            '18092176936' => '尹晨阳',
            '18096638481' => '唐星畅',
            '18099132026' => '杨俊宏',
            '18108895707' => '渡鸦25446',
            '18111876688' => '李哥',
            '18113968631' => '白先生',
            '18114617602' => '周启帆',
            '18118019880' => '马霄元',
            '18120873710' => '嘉晚饭',
            '18121408757' => '王先生',
            '18121959728' => '学渣k',
            '18128218939' => '冯杰辉',
            '18128679064' => '胸针',
            '18132370233' => '张生',
            '18134997631' => '姚劲宇',
            '18135612581' => '杨',
            '18154510997' => '庞保旋',
            '18155055758' => '黎冯成',
            '18157658005' => '秋天',
            '18158626679' => '陈先生',
            '18161859602' => '马彬',
            '18193168728' => '林先生',
            '18196630980' => '小宋',
            '18200368641' => '神经蛙',
            '18201655860' => '雪米',
            '18203660921' => '郝先生',
            '18210261806' => '贾德跃',
            '18216310119' => '陈生',
            '18221113351' => '卢生',
            '18221738160' => '陆言',
            '18232594632' => '李大海',
            '18244247297' => '姚程',
            '18252069097' => '张瑞民',
            '18263250118' => 'hyoer',
            '18280710351' => '曾依浩',
            '18290037469' => '秦子军',
            '18296251518' => '李宸亦',
            '18298469383' => '无呜呜',
            '18318390086' => '戈子衿',
            '18323695900' => '可小小',
            '18346399879' => '尹常青',
            '18352266796' => '刘薇',
            '18353813713' => '安国',
            '18361003097' => '周先生',
            '18366372251' => '王玉琦',
            '18369904958' => '陈大龙',
            '18375979232' => '白暗',
            '18411018910' => 'WSH',
            '18447281056' => '高',
            '18456393886' => '云时',
            '18481531765' => '冷先生',
            '18500176811' => '刘奥琪',
            '18500632567' => '会飞的猪',
            '18500781985' => '张屹',
            '18508305003' => '神里绫华',
            '18530696489' => '张先生',
            '18539673396' => '孙振',
            '18559206993' => '陈泱宇',
            '18569587912' => '天野梢',
            '18573446515' => '姜昊',
            '18578952172' => '商小涛',
            '18589399460' => '刘某',
            '18599993281' => '王超',
            '18601199895' => '吕先生',
            '18601397000' => '晴明',
            '18601491923' => '陈多鱼',
            '18605943210' => '刘先生',
            '18607311061' => '杨蒙',
            '18609630226' => '苏先生',
            '18616258128' => '郭丽军',
            '18618211239' => '张大',
            '18620079959' => '李豪',
            '18620811987' => '张敏',
            '18621366773' => '翟先生',
            '18621900627' => '闵先生',
            '18627216007' => '刘登丰',
            '18628122593' => '高先生',
            '18634856728' => '卖红薯',
            '18656753237' => '喻先生',
            '18664550736' => '刘波',
            '18668071926' => 'Karlcx',
            '18668088408' => '呉真',
            '18670638215' => '刘先生',
            '18671379257' => '柯青',
            '18672919561' => '杨',
            '18673177312' => '魏兆成',
            '18674826233' => '黄女士',
            '18679105016' => '万维',
            '18679182618' => '涂',
            '18679221679' => '刘坪',
            '18679623728' => '罗先生',
            '18682123395' => '陈先生',
            '18682735690' => 'AA',
            '18684882774' => '伍先生',
            '18694005447' => '杨先生',
            '18702203151' => '梁潇',
            '18707138714' => '董生',
            '18721104659' => '曹先生',
            '18732788314' => '董',
            '18753280082' => '蒋书文',
            '18765412236' => '江源翔',
            '18809483972' => '邓',
            '18810801516' => '田野',
            '18811537955' => '君墨',
            '18813150163' => '莫黑',
            '18817513142' => '孟德强',
            '18859561234' => 'panda',
            '18861537926' => '袅残烟pd',
            '18863608967' => '汪凯',
            '18868428128' => '朱先生',
            '18872779510' => '梁谦旺',
            '18897957130' => '小鹿',
            '18903593651' => '李涛',
            '18913712199' => '俞亦键',
            '18921259175' => '郑谦',
            '18929398255' => '王彦祺',
            '18932805700' => '胡先生',
            '18940650903' => '残影',
            '18955878606' => '张子默',
            '18962809983' => '袁勐霖',
            '18964443264' => '施欣缘',
            '18971081476' => '桑某',
            '18977669863' => '梁恒滔',
            '18980919013' => '要当科学家',
            '18998467214' => '陈琳煜',
            '19133713891' => '赵辉',
            '19370203161' => '荣岁',
            '19821213004' => '庞小海',
            '19821236351' => '姜文渊',
            '19917563949' => '陈嘉宇',
            '19937036152' => '王维',
            '19937181344' => '杨晓',
            '19953454919' => '刘',
            '19957134033' => '潘狗',
            '19968382469' => '李明',
            '19978332132' => '白刚',
            '17721670771' => '李超',
            '18562231230' => '李汶洲',
            '13116853880' => '高淇',
            '13113023146' => ''
        ];

        foreach ($receiver as $phone => $name) {
            $sms = $sms->add([
                'template'  => '11',
                'phones'    => $phone,
                'params'    => [
                    '收件人姓名'  => mb_substr(preg_replace('/\.(com|cn|org)/', '', $name), 0, 12),
                ]
            ]);
        }

        // $sms->send();
    }
}