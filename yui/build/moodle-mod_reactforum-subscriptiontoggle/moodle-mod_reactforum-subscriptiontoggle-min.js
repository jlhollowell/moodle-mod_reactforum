YUI.add("moodle-mod_reactforum-subscriptiontoggle",function(e,t){function n(){n.superclass.constructor.apply(this,arguments)}var r="moodle-mod_reactforum-subscriptiontoggle";e.extend(n,e.Base,{initializer:function(){e.delegate("click",this._toggleSubscription,e.config.doc.body,".discussionsubscription .discussiontoggle",this)},_toggleSubscription:function(t){var n=t.currentTarget;e.io(this.get("uri"),{data:{sesskey:M.cfg.sesskey,reactforumid:n.getData("reactforumid"),discussionid:n.getData("discussionid"),includetext:n.getData("includetext")},context:this,arguments:{clickedLink:n},on:{complete:this._handleCompletion}}),t.preventDefault()},_handleCompletion:function(t,n,r){var i;try{i=e.JSON.parse(n.response);if(i.error)return e.use("moodle-core-notification-ajaxexception",function(){return new M.core.ajaxException(i)}),this}catch(s){return e.use("moodle-core-notification-exception",function(){return new M.core.exception(s)}),this}if(!i.icon)return;var o=r.clickedLink.ancestor(".discussionsubscription");o&&o.set("innerHTML",i.icon)}},{NAME:"subscriptionToggle",ATTRS:{uri:{value:M.cfg.wwwroot+"/mod/reactforum/subscribe_ajax.php"}}});var i=e.namespace("M.mod_reactforum.subscriptiontoggle");i.init=function(e){return new n(e)}},"@VERSION@",{requires:["base-base","io-base"]});
