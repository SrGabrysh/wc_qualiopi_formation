/**
 * Admin JavaScript
 *
 * @package WcQualiopiFormation
 * @since 1.0.0
 */

(function ($) {
  "use strict";

  /**
   * WC Qualiopi Formation Admin
   */
  const WCQFAdmin = {
    /**
     * Initialize
     */
    init: function () {
      this.bindEvents();
      this.initLogsFilters();
    },

    /**
     * Bind events
     */
    bindEvents: function () {
      // Add event listeners here
      console.log("WCQF Admin initialized");
    },

    /**
     * Initialize logs filters functionality
     */
    initLogsFilters: function () {
      if (!$("#wcqf-filter-form").length) {
        return; // Not on logs page
      }

      // Filtrage temps réel des niveaux
      $(".wcqf-level-filter-checkbox").on("change", function () {
        WCQFAdmin.filterLogsByLevel();
      });

      // Fonction de filtrage par niveau
      this.filterLogsByLevel = function () {
        var selectedLevels = [];
        $(".wcqf-level-filter-checkbox:checked").each(function () {
          selectedLevels.push($(this).data("level"));
        });

        var stats = {
          total: 0,
          debug: 0,
          info: 0,
          warning: 0,
          error: 0,
          critical: 0,
        };

        // Afficher/masquer les lignes selon les niveaux sélectionnés
        $(".wcqf-logs-table-container tbody tr").each(function () {
          var $row = $(this);
          var level = $row.find(".wcqf-log-level").text().toLowerCase().trim();

          if (
            selectedLevels.length === 0 ||
            selectedLevels.indexOf(level) !== -1
          ) {
            $row.show();
            stats.total++;
            if (stats[level] !== undefined) {
              stats[level]++;
            }
          } else {
            $row.hide();
          }
        });

        // Mettre à jour les statistiques
        WCQFAdmin.updateStats(stats);

        // Afficher/masquer les stats par niveau
        $(".wcqf-stat-item[data-level]").each(function () {
          var level = $(this).data("level");
          if (
            selectedLevels.length === 0 ||
            selectedLevels.indexOf(level) !== -1
          ) {
            $(this).removeClass("hidden");
          } else {
            $(this).addClass("hidden");
          }
        });
      };

      // Mise à jour des compteurs de stats
      this.updateStats = function (stats) {
        $("#wcqf-total-count").text(stats.total);
        $("#wcqf-debug-count").text(stats.debug);
        $("#wcqf-info-count").text(stats.info);
        $("#wcqf-warning-count").text(stats.warning);
        $("#wcqf-error-count").text(stats.error);
        $("#wcqf-critical-count").text(stats.critical);
      };
    },
  };

  // Initialize on DOM ready
  $(document).ready(function () {
    WCQFAdmin.init();
  });
})(jQuery);
