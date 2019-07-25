(function ($) {

  if (!$('.schedule-dashboard__wrapper').length) {
    return;
  }

  if (window.OpenY.field_prgf_repeat_schedules_pref && window.OpenY.field_prgf_repeat_schedules_pref.length) {
    var locationPage = window.OpenY.field_prgf_repeat_schedules_pref[0] || '';
    if (locationPage) {
      $('.clear-all').attr('href', locationPage.url).removeClass('hidden');
    }
  }

  // PDF link show/hidden.
  if (window.OpenY.field_prgf_repeat_schedules_pdf && window.OpenY.field_prgf_repeat_schedules_pdf.length) {
    var pdfLink = window.OpenY.field_prgf_repeat_schedules_pdf[0] || '';
    if (pdfLink) {
      $('.btn-schedule-pdf')
        .removeClass('hidden')
        .attr('href', pdfLink.url);
    }
  }
  else {
    $('.btn-schedule-pdf-generate')
      .removeClass('hidden')
      .attr('href', drupalSettings.path.baseUrl + 'schedules/get-pdf' + window.location.search);
  }

  /* Check the settings of whether to display Instructor column or not */
  function displayInstructorOrNot() {
    var instructorDisplay = window.OpenY.field_prgf_repeat_schedule_instr[0].value;
    if (parseInt(instructorDisplay) != 1) {
      $('.instructor-column').remove();
    }
  }
  displayInstructorOrNot();

  // Set number of column classes.
  function calculateColumns() {
    if ($('.schedules-data__header').length > 0) {
      var colCount = $('.schedules-data__header > div').length;
      if ($('.schedules-data__row .register-btn').length === 0) {
        colCount = colCount - 1;
        $('.schedules-data__header > div').last().hide();
      }
      else {
        $('.schedules-data__header > div').last().show();
      }
      $('.schedules-data')
        .removeClass('schedules-data__cols-5')
        .removeClass('schedules-data__cols-6')
        .addClass('schedules-data__cols-' + colCount);
    }
  }

  function checkShowForwardArrow(date) {
    var limit = drupalSettings.openy_repeat.calendarLimitDays;
    if (!limit) {
      return true;
    }

    date = moment(new Date(date).toISOString());
    var now = moment();
    var diff = date.diff(now, 'days');

    return diff < (limit - 1);
  }

  Vue.config.devtools = true;

  var router = new VueRouter({
      mode: 'history',
      routes: []
  });

  // Retrieve the data via vue.js.
  new Vue({
    el: '#app',
    router: router,
    data: {
      showForwardArrow: true,
      table: [],
      date: '',
      room: [],
      locations: [],
      locationsLimit: [],
      categories: [],
      categoriesExcluded: [],
      categoriesLimit: [],
      className: [],
      locationPopup: {
        address: '',
        email: '',
        phone: '',
        title: ''
      },
      classPopup: {
        title: '',
        description: '',
        schedule: []
      },
      instructorPopup: {
        name: '',
        schedule: []
      }
    },
    created: function() {
      var component = this;
      // If there are any exclusions available from settings.
      var exclusionSettings = window.OpenY.field_prgf_repeat_schedule_excl || [];
      exclusionSettings.forEach(function(item){
        component.categoriesExcluded.push(item.title);
      });

      // If there is a preselected location, we'll hide filters and column.
      var limitLocations = window.OpenY.field_prgf_repeat_loc || [];
      if (limitLocations && limitLocations.length > 0) {
        // If we limit to one location. i.e. Andover from GroupExPro
        if (limitLocations.length == 1) {
          component.locations.push(limitLocations[0].title);
          $('.form-group-location').parent().hide();
          $('.location-column').remove();
        }
        else {
          limitLocations.forEach(function(element){
            component.locationsLimit.push(element.title);
          });

          $('.form-group-location .checkbox-wrapper input').each(function(){
            var value = $(this).attr('value');
            if (component.locationsLimit.indexOf(value) === -1) {
              $(this).parent().hide();
            }
          });
        }
      }

      // If there is preselected category, we hide filters and column.
      var limitCategories = window.OpenY.field_prgf_repeat_schedule_categ || [];
      if (limitCategories && limitCategories.length > 0) {
        // If we limit to one category. i.e. GroupExercises from GroupExPro
        if (limitCategories.length == 1) {
          component.categories.push(limitCategories[0].title);
          $('.form-group-category').parent().hide();
          $('.category-column').remove();
        }
        else {
          limitCategories.forEach(function(element){
            component.categoriesLimit.push(element.title);
          });

          $('.form-group-category .checkbox-wrapper input').each(function(){
            var value = $(this).attr('value');
            if (component.categoriesLimit.indexOf(value) === -1) {
              $(this).parent().hide();
            }
          });
        }
      }

      var dateGet = this.$route.query.date;
      if (dateGet) {
        var date = new Date(dateGet);
        date.setMinutes(date.getTimezoneOffset());
        this.date = date.toISOString();
      }
      else {
        this.date = moment().toISOString();
      }

      var locationsGet = this.$route.query.locations;
      if (locationsGet) {
        this.locations = locationsGet.split(',');
      }

      var categoriesGet = this.$route.query.categories;
      if (categoriesGet) {
        this.categories = categoriesGet.split(',');
      }

      this.runAjaxRequest();

      // We add watchers dynamically otherwise initially there will be
      // up to three requests as we are changing values while initializing
      // from GET query parameters.
      component.$watch('date', function(){ component.runAjaxRequest(); });
      component.$watch('locations', function(){ component.runAjaxRequest(); });
      component.$watch('categories', function(){ component.runAjaxRequest(); });
      component.$watch('classPopup', function(){ component.runAjaxRequest(); });
      component.$watch('instructorPopup', function(){ component.runAjaxRequest(); });
    },
    mounted: function() {
      /* It doesn't work if try to add datepicker in created. */
      var component = this;
      var limitDays = drupalSettings.openy_repeat.calendarLimitDays;
      $('#datepicker').datepicker({
        format: "MM d, DD",
        multidate: false,
        keyboardNavigation: false,
        forceParse: false,
        autoclose: false,
        todayHighlight: true,
        beforeShowDay: function (date) {
          if (!limitDays) {
            return true;
          }
          var diff = moment().diff(moment(date), 'days');
          return diff > -limitDays;
        }
      }).on('changeDate', function() {
        $('#datepicker2').datepicker("setDate",component.date = ($(this).datepicker('getDate')));
      });
      $('#datepicker2').datepicker();
      $('#datepicker .next').empty().append('<i class="fa fa-arrow-right"></i>');
      $('#datepicker .prev').empty().append('<i class="fa fa-arrow-left"></i>');
    },
    computed: {
      dateFormatted: function(){
        var date = new Date(this.date).toISOString();
        return moment(date).format('ddd, MMM D');
      },
      dateCalendarFormatted: function() {
        var date = new Date(this.date).toISOString();
        date = moment(date);
        var now = moment();
        var formatted = date.format('ddd, MM/D');
        if (date.format('MMDDYYYY') === now.format('MMDDYYYY')) {
          return 'Today (' + formatted + ')';
        }
        return formatted;
      },
      roomFilters: function() {
        var availableRooms = [];
        this.table.forEach(function(element){
          if (typeof availableRooms[element.location] === 'undefined') {
            availableRooms[element.location] = [];
          }
          if (element.room) {
            availableRooms[element.location][element.room] = element.room;
          }
        });

        var resultRooms = [];
        this.locations.forEach(function(location){
          if (typeof availableRooms[location] != 'undefined') {
            availableRooms[location] = Object.keys(availableRooms[location]);
            if (availableRooms[location].length > 0) {
              resultRooms[location] = availableRooms[location].sort();
            }
          }
        });

        return resultRooms;
      },
      classFilters: function() {
        var availableClasses = [];
        this.table.forEach(function(element) {
          if (element.class_info.title) {
            availableClasses[element.class_info.title] = element.class_info.title;
          }
        });

        // Already selected options.
        this.className.forEach(function(classname) {
          availableClasses[classname] = classname;
        });

        availableClasses = Object.keys(availableClasses);
        if (typeof availableClasses.alphanumSort !== 'undefined') {
          availableClasses.alphanumSort();
        }
        return availableClasses;
      },
      filteredTable: function() {
        var filterByRoom = [];

        this.room.forEach(function(roomItem) {
          var split = roomItem.split('||');
          var locationName = split[0];
          var roomName = split[1];
          if (typeof filterByRoom[locationName] === 'undefined') {
            filterByRoom[locationName] = [];
          }
          filterByRoom[locationName].push(roomName);
        });

        var locationsToFilter = Object.keys(filterByRoom);
        var resultTable = [];
        var self = this;
        this.table.forEach(function(item){
          if (locationsToFilter.length > 0) {
            // If we are not filtering rooms of this location -- skip it.
            if (locationsToFilter.indexOf(item.location) === -1) {
              return;
            }

            // Check if class in this room should be kept.
            if (filterByRoom[item.location].indexOf(item.room) === -1) {
              return;
            }
          }

          // Check if class fits classname filter.
          if (self.className.length > 0 && self.className.indexOf(item.class_info.title) === -1) {
            return;
          }

          resultTable.push(item);
        });

        return resultTable;
      }
    },
    methods: {
      runAjaxRequest: function() {
        var component = this;
        var date = moment(this.date).format('YYYY-MM-DD');

        var url = drupalSettings.path.baseUrl + 'schedules/get-event-data';
        url += this.locations.length > 0 ? '/' + encodeURIComponent(this.locations.join(',')) : '/0';
        url += this.categories.length > 0 ? '/' + encodeURIComponent(this.categories.join(',')) : '/0';
        url += date ? '/' + encodeURIComponent(date) : '';

        var query = [];
        if (this.categoriesExcluded.length > 0) {
          query.push('excl=' + encodeURIComponent(this.categoriesExcluded.join(',')));
        }
        if (this.categoriesLimit.length > 1) {
          query.push('limit=' + encodeURIComponent(this.categoriesLimit.join(',')));
        }

        if (query.length > 0) {
          url += '?' + query.join('&');
        }

        $('.schedules-empty_results').addClass('hidden');
        $('.schedules-loading').removeClass('hidden');

        $.getJSON(url, function(data) {
          component.table = data;
          if (data.length === 0) {
            $('.schedules-empty_results').removeClass('hidden');
          }
          $('.schedules-loading').addClass('hidden');
        });

        router.push({ query: {
            date: date,
            locations: this.locations.join(','),
            categories: this.categories.join(',')
          }});
      },

      toggleParentClass: function(event) {
        if (event.target.parentElement.classList.contains('skip-checked')) {
          event.target.parentElement.classList.remove('skip-checked');
          event.target.parentElement.classList.add('skip-t');
          if (!event.target.parentElement.classList.contains('skip-t')) {
            event.target.parentElement.classList.add('skip-t');
          }
        }

        else {
          event.target.parentElement.classList.toggle("skip-t");
          event.target.parentElement.classList.add('skip-checked');
          event.target.parentElement.classList.remove('skip-t');
          event.target.parentElement.classList.remove('collapse');
          event.target.parentElement.classList.remove('in');
        }
      },
      populatePopupLocation: function(index) {
        $('.modal').modal('hide');
        this.locationPopup = this.filteredTable[index].location_info;
      },
      populatePopupClass: function(sessionId) {
        var component = this;
        $('.modal').modal('hide');

        var bySessionUrl = drupalSettings.path.baseUrl + 'schedules/get-event-data-by-session/';
        bySessionUrl += encodeURIComponent(sessionId);

        $.getJSON(bySessionUrl, function(sessionData) {
          $('.schedules-loading').removeClass('hidden');
          var classItem = sessionData[0];
          component.classPopup = classItem.class_info;

          // For now we will search by clicked GroupEx class ID.
          // In GroupEx each class has separate ID for different locations.
          // So, in order to get class within all locations we need to pass
          // the array of class IDs.
          // @todo To be decided.
          var byClassUrl = drupalSettings.path.baseUrl + 'schedules/get-event-data-by-class/';
          byClassUrl += classItem.class;
          byClassUrl += component.locations.length > 0 ? '/' + encodeURIComponent(component.locations.join(',')) : '/0';
          byClassUrl += component.date ? '/' + encodeURIComponent(component.date) : '';

          $.getJSON(byClassUrl, function(classData) {
            component.classPopup.schedule = classData;
            $('.schedules-loading').addClass('hidden');
          });
        });
      },
      populatePopupInstructor: function(instructor) {
        $('.modal').modal('hide');
        var component = this;
        component.instructorPopup.name = instructor;

        var url = drupalSettings.path.baseUrl + 'schedules/get-event-data-by-instructor/';
        url += encodeURIComponent(instructor);
        url += this.locations.length > 0 ? '/' + encodeURIComponent(this.locations.join(',')) : '/0';
        url += this.date ? '/' + encodeURIComponent(this.date) : '';

        $('.schedules-loading').removeClass('hidden');
        $.getJSON(url, function(data) {
          component.instructorPopup.schedule = data;
          $('.schedules-loading').addClass('hidden');
        });
      },
      backOneDay: function() {
        var date = new Date(this.date).toISOString();
        this.date = moment(date).add(-1, 'day');
      },
      forwardOneDay: function() {
        var date = new Date(this.date).toISOString();
        this.date = moment(date).add(1, 'day');
      },
      addToCalendarDate: function(dateTime) {
        var dateTimeArray = dateTime.split(' ');
        var date = new Date(this.date).toISOString();

        return moment(date).format('YYYY-MM-D') + ' ' + dateTimeArray[1];
      },
      categoryExcluded: function(category) {
        return this.categoriesExcluded.indexOf(category) !== -1;
      },
      getRoomFilter: function(location) {
        if (typeof this.roomFilters[location] === 'undefined') {
          return false;
        }
        return this.roomFilters[location];
      },
      getClassFilter: function() {
        return this.classFilters;
      },
      generateId: function(string) {
        return string.replace(/[\W_]+/g, "-");
      },
      getFiltersCounter: function (filter) {
        if (!this[filter]) {
          return 0;
        }
        return this[filter].length;
      },
      clearFilters() {
        this.categories = [];
        this.locations = [];
        this.date = moment().format('YYYY-MM-DD');
      }
    },
    updated: function() {
      this.showForwardArrow = checkShowForwardArrow(this.date);

      calculateColumns();

      if (typeof(addtocalendar) !== 'undefined') {
        addtocalendar.load();
      }
      // Consider moving out of 'updated' handler.
      $('.btn-schedule-pdf-generate').off('click').on('click', function () {
        var rooms_checked = [],
            classnames_checked = [],
            limit = [];
        $('.checkbox-room-wrapper input').each(function () {
          if ($(this).is(':checked')) {
            rooms_checked.push(encodeURIComponent($(this).val()));
          }
        });
        rooms_checked = rooms_checked.join(',');

        $('.form-group-classname input:checked').each(function () {
          classnames_checked.push(encodeURIComponent($(this).val()));
        });

        var limitCategories = window.OpenY.field_prgf_repeat_schedule_categ || [];
        if (limitCategories && limitCategories.length > 0) {
          if (limitCategories.length == 1) {
            limit.push(limitCategories[0].title);
          }
          else {
            limitCategories.forEach(function(element){
              limit.push(element.title);
            });
          }
        }
        limit = limit.join(',');
        var pdf_query = window.location.search + '&rooms=' + rooms_checked + '&limit=' + limit;
        $(classnames_checked).each(function () {
          pdf_query += '&cn[]=' + this;
        });
        $('.btn-schedule-pdf-generate').attr('href', drupalSettings.path.baseUrl + 'schedules/get-pdf' + pdf_query);
      });
    },
    delimiters: ["${","}"]
  });

})(jQuery);
