jQuery(function ($) {
       
    var GimmeCalendarFeeds = {
       init: function() {
           if(typeof GimmeCalendarFeedsL10n == 'undefined')
           {
               console.log('localization failed for GimmeCalendarFeedsL10n');
               return;
           }

           GimmeCalendarFeeds.addBindings();
       },
       
       addBindings: function() {
           $('body').on('keydown', '.' + GimmeCalendarFeedsL10n.under_scored + '_name', GimmeCalendarFeeds.changeURL);
           $('body').on('click', '.' + GimmeCalendarFeedsL10n.under_scored + '_edit', GimmeCalendarFeeds.editClick);
           $('body').on('click', '.' + GimmeCalendarFeedsL10n.under_scored + '_delete', GimmeCalendarFeeds.deleteClick);
           $('body').on('change', '.' + GimmeCalendarFeedsL10n.under_scored + '_category', GimmeCalendarFeeds.categoryClick);
           $('body').on('click', '.error', GimmeCalendarFeeds.killError);
           $('div.' + GimmeCalendarFeedsL10n.under_scored).on('submit', 'form', GimmeCalendarFeeds.handleSubmit);
           $('#' + GimmeCalendarFeedsL10n.option + '_save').on('click', GimmeCalendarFeeds.saveOptions);
       },
       
       changeURL: function(e) {
            if(e.keyCode == 13)
            {
                e.preventDefault();
                GimmeCalendarFeeds.editClick(e);
                return false;
            }
            var $obj = $(e.target);
            var $div = $obj.closest('div');
            var text = $obj.attr('value');
            if(text.match(/^[a-zA-Z0-9_\-]+$/) || text == '')
            {
                $obj.removeClass('error');
                var $a = $('.' + GimmeCalendarFeedsL10n.under_scored + '_slug', $div);
                $a.text(text);
            }
            else
            {
                $obj.addClass('error');
            }
            // does that feed url already exist?
       },
       
       saveOptions: function(e) {
           var num_in_feed = $('#' + GimmeCalendarFeedsL10n.option + '_num_in_feed').attr('value');
           var look_ahead_size = $('#' + GimmeCalendarFeedsL10n.option + '_look_ahead_size').attr('value');
           var name = GimmeCalendarFeedsL10n.option + "[look_ahead_multiplier]";
           var look_ahead_multiplier = $("input[type='radio'][name='" + name + "']:checked").val();
       
           GimmeCalendarFeeds.performAjax({
                action: GimmeCalendarFeedsL10n.save_action,
                num_in_feed: num_in_feed,
                look_ahead_size: look_ahead_size,
                look_ahead_multiplier: look_ahead_multiplier            
            });
       },
       
       categoryClick: function(e) {
           var $obj = $(e.target);
           //var $option = $obj.find(":selected");
           var $div = $obj.closest('div');
           var $name = $('.' + GimmeCalendarFeedsL10n.under_scored + '_name', $div);
           if($name.attr('value') == "") // if no name chosen yet, use the category's slug
               $name.attr('value', $obj.attr('value'));
       },
       
       deleteClick: function(e) {
           var $obj = $(e.target);
           if(GimmeCalendarFeeds.toggleEdit($obj) == true)
               return;
           
           var id = $('.' + GimmeCalendarFeedsL10n.under_scored + '_id', $obj.parent()).val();
       
           if(id != null)
           {
                //console.log("send ajax delete request on " + id);
       
                // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
                GimmeCalendarFeeds.performAjax({
                    action: GimmeCalendarFeedsL10n.delete_action,
                    feed: id, // the number associated with this current feed we are deleting
                });
           }
       },
       
       editClick: function(e) {
           var $obj = $(e.target);
           var $li = $obj.closest('li');
           var id = $('.' + GimmeCalendarFeedsL10n.under_scored + '_id', $li).val();
       
           if(id != null)
           {
               var $button = $("span." + GimmeCalendarFeedsL10n.under_scored + "_edit", $li);
               if(GimmeCalendarFeeds.toggleEdit($button))
               {
                   //console.log("send ajax edit request on " + id);
       
                   var name =     $('.' + GimmeCalendarFeedsL10n.under_scored + '_name', $li).val();
                   var category = $('.' + GimmeCalendarFeedsL10n.under_scored + '_category', $li).val();
       
                   GimmeCalendarFeeds.performAjax({
                       action: GimmeCalendarFeedsL10n.edit_action,
                       feed: id, // the number associated with this current feed we are editing
                       name: name, // name of the feed we are editing
                       category: category // the categories of events that are associated with this feed
                   });
                }
           }
       },
       
       toggleEdit: function($obj) {
           //console.log( obj.text() );
           var retVal = false;
           if($obj.text() == "Delete") return retVal;
           
           var $edit_save       = ( $obj.text() == "Cancel" ? $obj.next() : $obj );
           var $delete_cancel   = ( $obj.text() == "Cancel" ? $obj : $obj.prev() );
           
           var $item = $obj.closest('.' + GimmeCalendarFeedsL10n.under_scored + '_item');
           $item.toggleClass(GimmeCalendarFeedsL10n.under_scored + '_editing');
       
           var editing = $item.hasClass(GimmeCalendarFeedsL10n.under_scored + '_editing');

           $edit_save.toggleClass("button-primary");
           $edit_save.text(editing ? "Save" : "Edit");
           $delete_cancel.text(editing ? "Cancel" : "Delete");
           return !editing;
       },
       
       handleEditResponse: function(result) {
            var $summary = $( '#' + GimmeCalendarFeedsL10n.under_scored + '_' + result.id + '_summary');
            var $edit_div = $( '#' + GimmeCalendarFeedsL10n.under_scored + '_' + result.id + '_edit_div');
            //var $event_span = $( '#' + GimmeCalendarFeedsL10n.under_scored + '_' + result.id + '_num_events');
            $summary.html(result.summary);
            $edit_div.html(result.edit_div);
            //$event_span.text(result.events);
       },
       
       handleSaveResponse: function(result) {
           $("div.wrap." + GimmeCalendarFeedsL10n.under_scored)
                .prepend("<div class='updated fade'>\n"+
                    "<p>Options have been saved.</p>\n" +
                    "</div>");
           $('div.updated').delay(6000).fadeOut();
       
           for(var i=0; i<result.events.length; i++)
           {
                var $event_span = $( '#' + GimmeCalendarFeedsL10n.under_scored + '_' + i + '_num_events');
                $event_span.text(result.events[i]);
           }
       },
       
       handleDeleteResponse: function(result) {
            // set the total results after deletion
            var $total = $( '#' + GimmeCalendarFeedsL10n.under_scored + '_total');
            $total.text(result.after);
       
            // animate deletion of the item
            var $item = $( '#' + GimmeCalendarFeedsL10n.under_scored + '_' + result.id + '_item');
            GimmeCalendarFeeds.animateDeletion( $item );
       
            // after deletion... I didn't reorder the indexes. And I should..
            // i used to get an error after deleting, then trying to add: Incorrect id: 1 != 1 - 1
            for(var i=result.id+1, j=result.id; i<=result.before; i++)
            {
                $( '#' + GimmeCalendarFeedsL10n.under_scored + '_' + i + '_item')
                    .attr('id', GimmeCalendarFeedsL10n.under_scored + '_' + j + '_item');
                
                $( '#' + GimmeCalendarFeedsL10n.under_scored + '_' + i + '_summary')
                    .attr('id', GimmeCalendarFeedsL10n.under_scored + '_' + j + '_summary');
                
                $( '#' + GimmeCalendarFeedsL10n.under_scored + '_' + i + '_edit_div')
                    .attr('id', GimmeCalendarFeedsL10n.under_scored + '_' + j + '_edit_div');

                $( '#' + GimmeCalendarFeedsL10n.under_scored + '_' + i + '_name')
                    .attr('name', GimmeCalendarFeedsL10n.under_scored + '[feeds][' + j + '][name]')
                    .attr('id', GimmeCalendarFeedsL10n.under_scored + '_' + j + '_name');

                $( '#' + GimmeCalendarFeedsL10n.under_scored + '_' + i + '_id')
                    .attr('value', j)
                    .attr('id', GimmeCalendarFeedsL10n.under_scored + '_' + j + '_id');

                $( '#' + GimmeCalendarFeedsL10n.under_scored + '_' + i + '_category')
                    .attr('name', GimmeCalendarFeedsL10n.under_scored + '[feeds][' + j + '][category][]')
                    .attr('id', GimmeCalendarFeedsL10n.under_scored + '_' + j + '_category');

                $( '#' + GimmeCalendarFeedsL10n.under_scored + '_' + i + '_name_label')
                    .attr('for', GimmeCalendarFeedsL10n.under_scored + '_' + j + '_name')
                    .attr('id', GimmeCalendarFeedsL10n.under_scored + '_' + j + '_name_label');

                $( '#' + GimmeCalendarFeedsL10n.under_scored + '_' + i + '_category_label')
                    .attr('for', GimmeCalendarFeedsL10n.under_scored + '_' + j + '_category')
                    .attr('id', GimmeCalendarFeedsL10n.under_scored + '_' + j + '_category_label');
                
                j++;
            }
       },
       
       animateDeletion: function($obj) {
            $obj.animate({ opacity: "0" }, 'fast')
                .delay(300)
                .animate({ height: "0", padding: "0", margin: "0" }, 'fast',
                    function() { //slide up
                        $obj.remove(); //then remove from the DOM
                    });
       },
       
       handleSubmit: function(e) {
            $obj = $('input[type=submit]').parent();
       
            // check the name is not empty
            var name =     $('.' + GimmeCalendarFeedsL10n.under_scored + '_name', $obj).val();
            if(name.length == 0)
            {
                GimmeCalendarFeeds.showError("The name of the feed cannot be empty.", null, null);
                return false;
            }
       
            // check the name is valid
            if(!name.match(/^[a-zA-Z0-9_\-]+$/))
            {
                GimmeCalendarFeeds.showError("The name of the feed contains invalid characters.", null, null);
                return false;
            }
       
            // check there is at least one category selected
            var category = $('.' + GimmeCalendarFeedsL10n.under_scored + '_category', $obj).val();
            if(category.length == 0)
            {
                GimmeCalendarFeeds.showError("You must select at least one event category.", null, null);
                return false;
            }
       
            // make sure the id is valid
            var id = $('.' + GimmeCalendarFeedsL10n.under_scored + '_id', $obj).val();
            try
            {
                var total = $('.' + GimmeCalendarFeedsL10n.under_scored + '_name').length;
                var i = parseInt(id);
                if(i != total-1)
                    throw ("Incorrect id: " + i + " != " + total + " - 1");
            }
            catch(e)
            {
                GimmeCalendarFeeds.showError(e, null, null);
                return false;
            }
       
            return true;
       },

       performAjax: function(data) {
            $.ajax({
                url: ajaxurl,
                data: data,
                type: 'POST',
                cache: false,
                success: GimmeCalendarFeeds.handleAjaxReponse,
                error: GimmeCalendarFeeds.handleAjaxReponse
            });
       },
       
       killError: function(e)
       {
           GimmeCalendarFeeds.animateDeletion($(e.target).closest('.error'));
       },
       
       showError: function(error, response, result)
       {
            if(error == null) error = "Oh no!";
            console.log(error);
       
            var formatted_json = "";
            if(response != null && typeof(response) == 'string' &&
               (result == null || typeof(result) != 'object' || (result != null && result.debug)))
            {
                formatted_json = "<pre>" + GimmeCalendarFeeds.formatJSON(response) + "</pre>\n";
                console.log(response);
            }
       
            $("div.wrap." + GimmeCalendarFeedsL10n.under_scored)
                .prepend("<div class='error'>\n" +
                           "<p>" + error + "</p>\n"+
                            formatted_json +
                        "</div>");
       
            if(result != null && typeof(result) == 'object' && typeof(result.id) == 'number')
            {
                console.log(result);
                $( '#' + GimmeCalendarFeedsL10n.under_scored + '_' + result.id + '_item')
                    .addClass(GimmeCalendarFeedsL10n.under_scored + '_item_error')
                    .delay(6000)
                    .removeClass(GimmeCalendarFeedsL10n.under_scored + '_item_error');
            }
       },
       
       handleAjaxReponse: function(response) {
           var result;
           try
           {
               result = JSON.parse(response);
               //console.log(result);
           }
           catch(e)
           {
               GimmeCalendarFeeds.showError(e, response, null);
           }
           
           if(result)
           {
               if(result.error)
               {
                   GimmeCalendarFeeds.showError(result.error, response, result);
               }
               else if(!result.success)
               {
                   GimmeCalendarFeeds.showError("No error message... but still unsuccessful.", response, result);
               }
               else if(result.functionName)
               {
                   if(result.successMsg)
                   {
                       $("div.wrap." + GimmeCalendarFeedsL10n.under_scored)
                            .prepend("<div class='updated fade'>\n"+
                                        "<p>" + result.successMsg + "</p>\n" +
                                    "</div>");
                       $('div.updated').delay(6000).fadeOut();
                   }
               
                   eval("GimmeCalendarFeeds." + result.functionName + "(result);");
               }
           }
       },
       
       formatJSON: function(json) {
            json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return json.replace(/([\}\]],?)/g, "</div>$1</div>").replace(/([\{\[])/g, "<div>$1<div>").replace(/,\"/g, ",</div><div>\"");
       }
   }; // end definition

   GimmeCalendarFeeds.init();
});
       

