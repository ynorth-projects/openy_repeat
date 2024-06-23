import VueRouter from 'vue-router';
Vue.use(VueRouter);

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
    if (parseInt(instructorDisplay) !== 1) {
      $('.instructor-column').remove();
    }
  }

  displayInstructorOrNot();

  // Set number of column classes.
  function calculateColumns() {
    if ($('.schedules-data__header').length > 0) {
      var cols = $('.schedules-data__header > div');
      var colCount = cols.length;
      if ($('.schedules-data__row .register-btn').length === 0) {
        colCount = colCount - 1;
        cols.last().hide();
      }
      else {
        cols.last().show();
      }
      $('.schedules-data')
        .removeClass('schedules-data__cols-5')
        .removeClass('schedules-data__cols-6')
        .addClass('schedules-data__cols-' + colCount);
    }
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
      itemsPerPage: 25,
      currentPage: 1,
      table: [],
      date: '',
      room: [],
      locations: [],
      locationsLimit: [],
      categories: [],
      categoriesExcluded: [],
      categoriesLimit: [],
      className: [],
      instructorName: '',
      isLoading: true,
      weekHasResults: false,
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
      },
      instructorPopup_schedule: [],
      filterTabs: {
        date: 0,
        category: 1,
        location: 0,
        className: 0,
        instructorName: 0,
      }
    },
    created: function () {
      var component = this;
      // If there are any exclusions available from settings.
      var exclusionSettings = window.OpenY.field_prgf_repeat_schedule_excl || [];
      exclusionSettings.forEach((item) => {
        component.categoriesExcluded.push(item.title);
      });

      // If there is a preselected location, we'll hide filters and column.
      var limitLocations = window.OpenY.field_prgf_repeat_loc || [];
      if (limitLocations && limitLocations.length > 0) {
        // If we limit to one location. i.e. Andover from GroupExPro
        if (limitLocations.length === 1) {
          component.locations.push(limitLocations[0].title);
          $('.form-group-location').parent().hide();
          $('.location-column').remove();
        }
        else {
          limitLocations.forEach((element) => {
            component.locationsLimit.push(element.title);
          });

          $('.form-group-location .checkbox-wrapper input').each(function () {
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
        if (limitCategories.length === 1) {
          component.categories.push(limitCategories[0].title);
          component.categoriesLimit.push(limitCategories[0].title);
          $('.form-group-category').parent().hide();
          $('.category-column').remove();
        }
        else {
          limitCategories.forEach((element) => {
            component.categoriesLimit.push(element.title);
          });

          $('.form-group-category .checkbox-wrapper input').each(function () {
            var value = $(this).attr('value');
            if (component.categoriesLimit.indexOf(value) === -1) {
              $(this).parent().hide();
            }
          });
        }
      }

      var dateGet = this.$route.query.date;
      if (dateGet) {
        this.date = moment(dateGet).toISOString();
      }
      else {
        this.date = moment().toISOString();
      }

      var locationsGet = this.$route.query.locations;
      if (locationsGet) {
        this.locations = locationsGet.split(';');
      }

      var classNamesGet = this.$route.query.cn;
      if (classNamesGet) {
        this.className = classNamesGet.split(';');
      }

      var instructorNamesGet = this.$route.query.inst;
      if (instructorNamesGet) {
        this.instructorName = instructorNamesGet.split(';');
      }

      var categoriesGet = this.$route.query.categories;
      if (categoriesGet) {
        this.categories = categoriesGet.split(';');
      }

      this.runAjaxRequest();
      this.runAjaxWeekResultsRequest();
      // We add watchers dynamically otherwise initially there will be
      // up to three requests as we are changing values while initializing
      // from GET query parameters.

      this.$watch('date', () => {
        this.runAjaxRequest();
        this.runAjaxWeekResultsRequest();
        this.resetPager();
      });

      this.$watch('locations', function () {
        component.runAjaxRequest();
        component.resetPager();
        component.resetRooms();
      });
      this.$watch('categories', function () {
        component.runAjaxRequest();
        component.resetPager();
      });

      this.$watch('className', function () {
        component.runAjaxRequest();
        component.resetPager();
      });
      this.$watch('instructorName', function () {
        component.runAjaxRequest();
        component.resetPager();
      });
      this.$watch('classPopup', function () {
        component.runAjaxRequest();
      });
      this.$watch('instructorPopup', function () {
        component.runAjaxRequest();
      });
      this.$watch("instructorPopup_schedule", function () {
        component.runAjaxRequest();
      });
    },
    mounted: function () {
      /* It doesn't work if try to add datepicker in created. */
      var component = this;
      var limitDays = drupalSettings.openy_repeat.calendarLimitDays;
      $('#datepicker2').datepicker();
      $('#datepicker').datepicker({
        format: "yyyy-mm-dd",
        multidate: false,
        keyboardNavigation: false,
        forceParse: false,
        autoclose: false,
        todayHighlight: true,
        beforeShowDay: function (date) {
          if (!limitDays) {
            return true;
          }

          // Get diff between current date and argument date.
          var diff = moment().diff(moment(date), 'days');

          // Disable past dates.
          if (diff > 0) {
            return false;
          }

          return diff > -limitDays;
        }
      }).on('changeDate', function (event) {
        // In case we use unselect date.
        var date = new Date().toISOString();
        if (event.format()) {
          var parsed = moment(event.format(), 'YYYY-MM-DD');
          date = parsed.toISOString();
        }
        component.date = date;
      }).datepicker("setDate", moment(component.date).format('YYYY-MM-DD'));

      $('#datepicker .next').empty().append('<i class="fa fa-arrow-right"></i>');
      $('#datepicker .prev').empty().append('<i class="fa fa-arrow-left"></i>');
    },
    computed: {
      dateFormatted: function () {
        return moment(this.date).format('ddd, MMM D');
      },
      dateCalendarFormatted: function () {
        var formatted = moment(this.date).format('ddd MMM D');
        if (moment(this.date).format('MMDDYYYY') === moment().format('MMDDYYYY')) {
          return 'Today (' + formatted + ')';
        }
        return formatted;
      },
      roomFilters: function () {
        var availableRooms = [];
        this.table.forEach((element) => {
          if (typeof availableRooms[element.location] === 'undefined') {
            availableRooms[element.location] = [];
          }
          if (element.room) {
            availableRooms[element.location][element.room] = element.room;
          }
        });

        var resultRooms = [];
        this.locations.forEach((location) => {
          if (typeof availableRooms[location] !== 'undefined') {
            availableRooms[location] = Object.keys(availableRooms[location]);
            if (availableRooms[location].length > 0) {
              resultRooms[location] = availableRooms[location].sort();
            }
          }
        });

        return resultRooms;
      },
      classFilters: function () {
        var availableClasses = [];
        this.table.forEach(function (element) {
          if (element.class_info.title) {
            availableClasses[element.class_info.title] = element.class_info.title;
          }
        });

        // Already selected options.
        this.className.forEach(function (classname) {
          availableClasses[classname] = classname;
        });

        availableClasses = Object.keys(availableClasses);
        if (typeof availableClasses.alphanumSort !== 'undefined') {
          availableClasses.alphanumSort();
        }
        return availableClasses;
      },
      instructorFilters: function () {
        var availableInstructors = [];
        this.table.forEach(function (element) {
          if (element.instructor) {
            availableInstructors[element.instructor] = element.instructor;
          }
        });

        // Already selected options.
        if (this.instructorName instanceof Array) {
          this.instructorName.forEach(function (instructorname) {
            availableInstructors[instructorname] = instructorname;
          });
        }

        availableInstructors = Object.keys(availableInstructors);
        if (typeof availableInstructors.alphanumSort !== 'undefined') {
          availableInstructors.alphanumSort();
        }
        return availableInstructors;
      },
      filteredTable: function () {
        let filterByRoom = [];
        this.room.forEach(function (roomItem) {
          var split = roomItem.split('||');
          var locationName = split[0];
          var roomName = split[1];
          if (typeof filterByRoom[locationName] === 'undefined') {
            filterByRoom[locationName] = [];
          }
          filterByRoom[locationName].push(roomName);
        });

        let locationsToFilter = Object.keys(filterByRoom);
        var resultTable = [];
        let self = this;
        this.table.forEach(function (item) {
          item.cancelled = item.name.indexOf('CANCELLED');
          if (self.locations.length > 0 && item && typeof (self.locations) !== 'undefined') {
            // If we are not filtering rooms of this location -- skip it.
            if (self.locations.indexOf(item.location) === -1) {
              return;
            }

            // Check if class in this room should be kept.
            if (locationsToFilter.length > 0 && typeof (filterByRoom[item.location]) !== 'undefined') {
              if (filterByRoom[item.location].indexOf(item.room) === -1) {
                return;
              }
            }
          }
          // Check if class fits classname filter.
          if (self.className.length > 0 && self.className.indexOf(item.class_info.title) === -1) {
            return;
          }
          // Check if instructor fits instructor filter.
          if (self.instructorName.length > 0 && self.instructorName.indexOf(item.instructor) === -1) {
            return;
          }
          resultTable.push(item);
        });

        // Hide cancelled sessions.
        resultTable = resultTable.filter(function (item) {
          return item.name.indexOf('CANCELLED') < 0;
        });

        return resultTable;
      },
      pagedTable: function () {
        var from = (this.currentPage - 1) * this.itemsPerPage;
        if (this.currentPage === 1) {
          from = 0;
        }

        var to = from + this.itemsPerPage;
        if (this.currentPage === this.getTotalPages()) {
          to = this.getResultsCount();
        }

        return this.filteredTable.slice(from, to);
      }
    },
    methods: {
      prepareRequest: function () {
        var requestString = '';
        var date = moment(this.date).format('YYYY-MM-DD');

        var locsUrl = '/0';
        if (this.locations.length > 0) {
          locsUrl = '/' + encodeURIComponent(this.locations.join(';'));
        }
        else if (this.locationsLimit.length > 0) {
          locsUrl = '/' + encodeURIComponent(this.locationsLimit.join(';'));
        }
        requestString += locsUrl;
        requestString += this.categories.length > 0 ? '/' + encodeURIComponent(this.categories.join(';')) : '/0';
        requestString += date ? '/' + encodeURIComponent(date) : '';

        var query = [];
        if (this.categoriesExcluded.length > 0) {
          query.push('excl=' + encodeURIComponent(this.categoriesExcluded.join(';')));
        }
        if (this.categoriesLimit.length >= 1) {
          query.push('limit=' + encodeURIComponent(this.categoriesLimit.join(';')));
        }

        if (query.length > 0) {
          requestString += '?' + query.join('&');
        }

        return requestString;
      },
      runAjaxRequest: function () {
        this.isLoading = true;
        var component = this;
        var date = moment(this.date).format('YYYY-MM-DD');

        var url = drupalSettings.path.baseUrl + 'schedules/get-event-data' + this.prepareRequest();

        $('.schedules-empty_results').addClass('hidden');

        $.getJSON(url, function (data) {
          component.table = data;
          if (data.length === 0) {
            $('.schedules-empty_results').removeClass('hidden');
          }
          component.isLoading = false;
        });

        var instructor = this.instructorName;
        if (typeof instructor === 'string') {
          instructor = [instructor];
        }

        router.push({
          query: {
            date: date,
            locations: this.locations.join(';'),
            categories: this.categories.join(';'),
            cn: this.className.join(';'),
            inst: instructor.join(';')
          }
        }).catch(err => {
          console.log(err);
        });
      },
      runAjaxWeekResultsRequest: function (){
        var url = drupalSettings.path.baseUrl + 'schedules/get-week-has-events' + this.prepareRequest();
        $.getJSON(url, (data) => {
          this.weekHasResults = data;
        });
      },
      toggleTab: function (filter) {
        var component = this;
        var status = component.filterTabs[filter];

        // In case of collapsing.
        if (status === 1) {
          component.filterTabs[filter] = 0;
        }

        // In case of expanding.
        if (status === 0) {
          Object.keys(component.filterTabs).forEach(function (item) {
            if (item !== filter) {
              component.filterTabs[item] = 0;
            }
            else {
              component.filterTabs[item] = 1;
            }
          });
        }
      },
      showLocationFilterItem: function (location) {
        var component = this;

        // Always show checked component.
        if (component.locations.indexOf(location) !== -1) {
          return true;
        }

        // Show all items if tab is expanded.
        return this.filterTabs.location === 1;
      },
      populatePopupLocation: function (index) {
        $('.modal').modal('hide');
        this.locationPopup = this.filteredTable[index].location_info;
      },
      populatePopupClass: function (sessionId) {
        var component = this;
        var date = moment(this.date).format("YYYY-MM-DD");
        component.classPopup = {};
        // Make sure popups work OK on all devices.
        $('.modal').modal('hide');
        $('.schedule-dashboard__modal--instructor')
          .on('shown.bs.modal', function () {
            $('.nav-global').addClass('hidden-xs');
            $('body').addClass('scroll-not');
          })
          .on('hidden.bs.modal', function () {
            $('.nav-global').removeClass('hidden-xs');
            $('body').removeClass('scroll-not').addClass('pr-none');
          });

        $('.schedule-dashboard__modal--class')
          .on('shown.bs.modal', function () {
            $('body').addClass('scroll-not');
          })
          .on('hidden.bs.modal', function () {
            $('body').removeClass('scroll-not').addClass('pr-none');
          });

        var bySessionUrl = drupalSettings.path.baseUrl + 'schedules/get-event-data-by-session/';
        bySessionUrl += encodeURIComponent(sessionId);
        bySessionUrl += this.locations.length > 0 ? '/' + encodeURIComponent(this.locations.join(';')) : '/0';
        bySessionUrl += this.date ? '/' + encodeURIComponent(this.date) : '';

        $.getJSON(bySessionUrl, function (data) {
          var loader = $('.schedules-loading');
          loader.removeClass('hidden');
          component.classPopup = data[0].class_info;
          component.classPopup.schedule = data.filter(function (item) {
            item.cancelled = item.name.indexOf('CANCELLED');
            if (component.locations.length > 0) {
              return component.locations.includes(item.location);
            }
            else {
              return true;
            }
          });
          loader.addClass('hidden');
        });
      },
      populatePopupInstructor: function (instructor) {
        var component = this;
        component.instructorPopup = {};
        component.instructorPopup.name = instructor;

        // Make sure popups work OK on all devices.
        $('.modal').modal('hide');
        $('.schedule-dashboard__modal--class')
          .on('shown.bs.modal', function () {
            $('.nav-global').addClass('hidden-xs');
          })
          .on('hidden.bs.modal', function () {
            $('.nav-global').removeClass('hidden-xs');
          });

        var url = drupalSettings.path.baseUrl + 'schedules/get-event-data-by-instructor/';
        url += encodeURIComponent(instructor);
        url += this.locations.length > 0 ? '/' + encodeURIComponent(this.locations.join(';')) : '/0';
        url += this.date ? '/' + encodeURIComponent(this.date) : '';

        $('.schedules-loading').removeClass('hidden');
        $.getJSON(url, function (data) {
          component.instructorPopup_schedule = data;
          component.instructorPopup_schedule = data.filter(function (item) {
            item.cancelled = item.name.indexOf('CANCELLED');
            if (component.locations.length > 0) {
              return component.locations.includes(item.location);
            }
            else {
              return true;
            }

          });
          $('.schedules-loading').addClass('hidden');
        });
      },
      backOneDay: function () {
        var date = new Date(this.date).toISOString();
        this.date = moment(date).add(-1, 'day');
      },
      forwardOneDay: function () {
        var date = new Date(this.date).toISOString();
        this.date = moment(date).add(1, 'day');
      },
      addToCalendarDate: function (dateTime) {
        var dateTimeArray = dateTime.split(' ');
        var date = new Date(this.date).toISOString();

        return moment(date).format('YYYY-MM-D') + ' ' + dateTimeArray[1];
      },
      categoryExcluded: function (category) {
        return this.categoriesExcluded.indexOf(category) !== -1;
      },
      getRoomFilter: function (location) {
        if (typeof this.roomFilters[location] === 'undefined') {
          return false;
        }
        return this.roomFilters[location];
      },
      getClassFilter: function () {
        return this.classFilters;
      },
      getInstructorFilter: function () {
        return this.instructorFilters;
      },
      generateId: function (string) {
        return string.replace(/[\W_]+/g, "-");
      },
      getFiltersCounter: function (filter) {
        if (!this[filter]) {
          return 0;
        }
        return this[filter].length;
      },
      clearFilters: function () {
        this.categories = [];
        this.className = [];
        this.instructorName = '';

        // We should not reset location pre-selected in the paragraph.
        var limitLocations = window.OpenY.field_prgf_repeat_loc || [];
        if (!limitLocations.length) {
          this.locations = [];
        }

        this.date = moment().format('YYYY-MM-DD');
        this.resetPager();
      },
      getResultsCount: function () {
        return this.filteredTable.length;
      },
      getTotalPages: function () {
        var count = 1;

        var itemsTotal = this.getResultsCount();
        if (itemsTotal > this.itemsPerPage) {
          count = Math.ceil(itemsTotal / this.itemsPerPage);
        }

        return count;
      },
      loadFirstPage: function () {
        this.currentPage = 1;
        this.scrollToTop();
      },
      loadPrevPage: function () {
        this.currentPage = this.currentPage - 1;
        this.scrollToTop();
      },
      loadNextPage: function () {
        this.currentPage = this.currentPage + 1;
        this.scrollToTop();
      },
      loadLastPage: function () {
        this.currentPage = this.getTotalPages();
        this.scrollToTop();
      },
      resetPager: function () {
        this.currentPage = 1;
        this.scrollToTop();
      },
      resetRooms: function () {
        var component = this;
        // Empty all rooms if there is no selected location.
        if (this.locations.length === 0) {
          this.room = [];
          return;
        }

        // Loop over each room and remove if corresponding location is
        // unselected.
        this.room.forEach(function (item) {
          var parts = item.split('||');
          if (component.locations.indexOf(parts[0]) === -1) {
            delete component.room[item];
          }
        });
      },

      scrollToTop: function () {
        if (screen.width <= 991) {
          $('html, body').animate({ scrollTop: $('.schedule-dashboard__content').offset().top - 200 }, 500);
        }
      },
      showBackArrow: function () {
        var diff = moment().diff(moment(this.date), 'hours');
        return this.isLoading ? false : diff < 0;
      },
      showForwardArrow: function () {
        var limit = drupalSettings.openy_repeat.calendarLimitDays;
        if (!limit) {
          return true;
        }

        var date = moment(this.date);
        var now = moment();
        var diff = date.diff(now, 'days');
        return this.isLoading ? false : diff < (limit - 1);
      },
      showAddToCalendar: function (index, selector) {
        $(selector + " .atcb-link").each(function (i) {
          if (index === i) {
            if (!$(this).hasClass('open')) {
              $(".atcb-link").removeClass('open').parent().find('ul').removeClass('active').css('visibility', 'hidden !important');
              $(this).addClass('open').parent().find('ul').addClass('active').css('visibility', 'visible !important').find('.atcb-item-link:eq(0)').focus();
            }
            else {
              $(this).removeClass('open').parent().find('ul').removeClass('active').css('visibility', 'hidden !important');
            }
          }
        });
      },
      showEndTime: function () {
        if (window.OpenY.field_prgf_repeat_schedule_end && window.OpenY.field_prgf_repeat_schedule_end.length) {
          return window.OpenY.field_prgf_repeat_schedule_end[0].value || 0;
        }
        else {
          return 0;
        }
      }
    },
    updated: function () {
      calculateColumns();

      if (typeof (addtocalendar) !== 'undefined') {
        addtocalendar.load();
      }
      // Consider moving out of 'updated' handler.
      $('.btn-schedule-pdf-generate').off('click').on('click', function () {
        var rooms_checked = [],
          classnames_checked = [],
          limit = [],
          instructor_selected = [];
        $('.checkbox-room-wrapper input').each(function () {
          if ($(this).is(':checked')) {
            rooms_checked.push(encodeURIComponent($(this).val()));
          }
        });
        rooms_checked = rooms_checked.join(';');

        $('.form-group-classname input:checked').each(function () {
          classnames_checked.push(encodeURIComponent($(this).val()));
        });

        $('.form-group-instructor-name option:selected').each(function () {
          instructor_selected.push(encodeURIComponent($(this).val()));
        });

        var limitCategories = window.OpenY.field_prgf_repeat_schedule_categ || [];
        if (limitCategories && limitCategories.length > 0) {
          if (limitCategories.length === 1) {
            limit.push(encodeURIComponent(limitCategories[0].title));
          }
          else {
            limitCategories.forEach(function (element) {
              limit.push(encodeURIComponent(element.title));
            });
          }
        }
        limit = limit.join(';');
        var pdf_query = window.location.search + '&rooms=' + rooms_checked + '&limit=' + limit;
        $(classnames_checked).each(function () {
          pdf_query += '&cn[]=' + this;
        });
        $(instructor_selected).each(function () {
          pdf_query += '&inst[]=' + this;
        });
        $('.btn-schedule-pdf-generate').attr('href', drupalSettings.path.baseUrl + 'schedules/get-pdf' + pdf_query);
      });
    },
    delimiters: ["${", "}"]
  });

})(jQuery);