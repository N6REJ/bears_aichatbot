<?php
/**
 * Bears AI Chatbot
 *
 * @version 2025.09.19
 * @package Bears AI Chatbot
 * @author N6REJ
 * @email troy@hallhome.us
 * @website https://www.hallhome.us
 * @copyright Copyright (C) 2025 Troy Hall (N6REJ)
 * @license GNU General Public License version 3 or later; see LICENSE.txt
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\TextareaField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

class JFormFieldIgnorewords extends TextareaField
{
    protected $type = 'Ignorewords';

    protected function getInput()
    {
        // Get the current value
        $value = $this->value;
        
        // If the value is empty or is the language constant, use the translated default
        if (empty($value) || $value === 'MOD_BEARS_AICHATBOT_IGNORE_WORDS_DEFAULT') {
            // Ensure module language file is loaded so translation resolves in admin and site contexts
            $lang = Factory::getLanguage();
            // Try site module path first
            $lang->load('mod_bears_aichatbot', JPATH_SITE . '/modules/mod_bears_aichatbot', null, false, true);
            // Fallback to administrator base
            $lang->load('mod_bears_aichatbot', JPATH_ADMINISTRATOR, null, false, true);
            $defaultIgnoreWords = Text::_('MOD_BEARS_AICHATBOT_IGNORE_WORDS_DEFAULT');
            
            // If translation failed, use hardcoded English ignore words
            if ($defaultIgnoreWords === 'MOD_BEARS_AICHATBOT_IGNORE_WORDS_DEFAULT') {
                $defaultIgnoreWords = 'a,able,about,above,across,act,actually,add,after,again,against,age,ago,agree,ah,all,almost,alone,along,already,also,although,always,am,among,an,and,anger,angry,animal,another,answer,any,anyone,anything,anyway,appear,are,area,around,as,ask,at,attack,away,back,bad,bag,ball,be,beat,beautiful,became,because,become,bed,been,before,began,begin,being,believe,below,best,better,between,big,bit,black,blue,boat,body,book,both,bottom,box,boy,break,bring,brother,brought,brown,build,building,built,burn,business,but,buy,by,call,came,can,cannot,car,care,carry,case,cat,catch,caught,cause,change,character,check,child,children,choose,city,class,clear,close,cold,color,come,common,complete,consider,control,cool,corner,cost,could,country,couple,course,cover,create,cry,cut,dark,day,dead,deal,death,decide,deep,did,die,difference,different,difficult,dinner,direction,do,does,dog,done,door,down,draw,dream,drive,drop,during,each,early,earth,easy,eat,effect,end,enough,enter,entire,even,evening,ever,every,everyone,everything,exactly,example,experience,eye,face,fact,fall,family,far,fast,father,fear,feel,feeling,feet,fell,felt,few,field,fight,figure,fill,final,finally,find,fine,finger,finish,fire,first,fish,five,floor,fly,follow,food,foot,for,force,forget,form,found,four,free,friend,from,front,full,fun,game,gave,get,girl,give,go,god,going,gold,good,got,government,great,green,ground,group,grow,grown,gun,guy,had,hair,half,hand,happen,happy,hard,has,have,he,head,hear,heard,heart,heat,heavy,held,help,her,here,high,him,his,hit,hold,home,hope,horse,hot,hour,house,how,however,huge,human,hundred,hurt,i,idea,if,important,in,inside,instead,interest,into,is,it,its,job,join,just,keep,kept,kill,kind,knew,know,known,land,language,large,last,late,later,laugh,lay,lead,learn,leave,left,let,letter,life,light,like,line,list,listen,little,live,long,look,lose,lost,lot,love,low,made,make,man,many,may,me,mean,meet,member,men,might,mind,minute,miss,moment,money,month,more,morning,most,mother,move,music,must,my,name,nation,nature,near,need,never,new,news,next,night,no,none,nor,north,not,note,nothing,notice,now,number,of,off,often,oh,ok,old,on,once,one,only,open,or,order,other,our,out,outside,over,own,page,pain,paper,part,past,pay,peace,people,perfect,perhaps,person,pick,picture,piece,place,plan,plant,play,please,point,police,political,poor,popular,power,present,president,pretty,probably,problem,process,produce,product,program,provide,public,pull,purpose,push,put,question,quickly,quite,race,reach,read,ready,real,reality,realize,really,reason,receive,record,red,remember,remove,report,represent,require,rest,result,return,right,rise,road,rock,role,room,rule,run,safe,same,save,say,scene,school,science,sea,season,seat,second,section,see,seem,sell,send,sense,sent,series,serious,serve,service,set,several,sex,shake,share,she,shoot,short,shot,should,show,side,sign,similar,simple,simply,since,sing,single,sister,sit,site,situation,six,size,skill,skin,small,smile,so,social,society,soldier,some,someone,something,sometimes,son,song,soon,sort,sound,south,space,speak,special,specific,spend,spent,split,spring,staff,stage,stand,standard,star,start,state,station,stay,step,still,stock,stop,store,story,street,strong,structure,student,study,stuff,style,subject,such,suddenly,suffer,suggest,summer,support,sure,surface,system,table,take,talk,task,tax,teach,teacher,team,technology,television,tell,ten,term,test,than,thank,that,the,their,them,themselves,then,there,these,they,thing,think,third,this,those,though,thought,thousand,three,through,throughout,throw,thus,time,to,today,together,tonight,too,took,top,total,tough,toward,town,trade,training,travel,treat,treatment,tree,trial,trip,trouble,true,truth,try,turn,tv,two,type,under,understand,unit,until,up,upon,us,use,usually,value,various,very,victim,view,violence,visit,voice,wait,walk,wall,want,war,watch,water,way,we,weapon,wear,week,weight,well,west,what,whatever,when,where,whether,which,while,white,who,whole,whom,whose,why,wide,wife,will,win,wind,window,wish,with,within,without,woman,wonder,word,work,worker,world,worry,worse,worst,would,write,writer,wrong,yard,yeah,year,yes,yet,you,young,your,yourself';
            }
            
            $this->value = $defaultIgnoreWords;
        }
        
        return parent::getInput();
    }
}
