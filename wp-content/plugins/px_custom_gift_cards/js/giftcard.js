jQuery(function ($) {
  let selectedValue = null;
  let selectedPrice = null;
  let selectedLabel = "";

  const ajaxSettings = typeof pxgc_ajax === "object" ? pxgc_ajax : {};
  const viewCartText = ajaxSettings.view_cart_text || "View cart";
  const addedText = ajaxSettings.added_text || "has been added to your cart,";
  const defaultProductLabel = ajaxSettings.default_product_label || "Gift card";

  const clearAllNotices = () => {
    $(".pxgc_notice").removeClass("pxgc_notice--error").hide().html("");
  };

  const resetButtonState = (btn) => {
    btn.find(".loader").hide();
    btn.find(".pxgc-btn-text").show();
    btn.prop("disabled", false);
  };

  /* TAB SWITCHING */
  $(".pxgc-tab-btn").on("click", function () {
    if ($(this).hasClass("active")) return;

    $(".pxgc-tab-btn").removeClass("active");
    $(this).addClass("active");

    const target = $(this).data("tab");
    const newPanel = $("#pxgc-tab-" + target);
    const oldPanel = $(".pxgc-tab-content.active");

    selectedValue = null;
    selectedPrice = null;
    selectedLabel = "";
    $("#pxgc_price").text("");
    $(".pxgc_select").val("");
    clearAllNotices();

    oldPanel.fadeOut(200, function () {
      oldPanel.removeClass("active");
      newPanel.fadeIn(200).addClass("active");
    });
  });

  /* SELECT CHANGE */
  $(".pxgc_select").on("change", function () {
    const selected = $("option:selected", this);
    selectedValue = selected.val();
    selectedPrice = selected.data("price");
    selectedLabel = $.trim(selected.text());

    if (selectedPrice) {
      $("#pxgc_price").text("??" + parseFloat(selectedPrice).toFixed(2));
    } else {
      $("#pxgc_price").text("??0.00");
    }

    clearAllNotices();
  });

  // ADD TO CART BUTTON
  $(".pxgc_add_button").on("click", function (e) {
    e.preventDefault();

    const btn = $(this);
    const container = btn.closest(".pxgc_button_section");
    const notice = container.find(".pxgc_notice");

    if (!selectedValue || !selectedPrice) {
      alert("Please select a consultation or class.");
      return;
    }

    // Hide button text, show spinner
    btn.find(".pxgc-btn-text").hide();
    btn.find(".loader").show();
    btn.prop("disabled", true);
    notice.removeClass("pxgc_notice--error").hide().html("");

    $.ajax({
      url: ajaxSettings.ajax_url || window.location.href,
      type: "POST",
      data: {
        action: "pxgc_add_to_cart",
        selected_value: selectedValue,
        price: selectedPrice,
      },
      success: function (response) {
        resetButtonState(btn);
        const data = response && response.data ? response.data : {};

        if (response && response.success) {
          const productName =
            data.item_name || selectedLabel || defaultProductLabel;
          const cartUrl = data.cart_url || ajaxSettings.cart_url || "#";

          const message =
            productName +
            " " +
            addedText +
            ' <a href="' +
            cartUrl +
            '">' +
            viewCartText +
            "</a>";

          notice
            .removeClass("pxgc_notice--error")
            .html(message)
            .fadeIn(200);
        } else {
          const errorMsg =
            data.message || "Something went wrong. Please try again.";
          notice
            .addClass("pxgc_notice--error")
            .text(errorMsg)
            .fadeIn(200);
        }
      },
      error: function () {
        resetButtonState(btn);
        notice
          .addClass("pxgc_notice--error")
          .text("Request failed. Please try again.")
          .fadeIn(200);
      },
    });
  });
});
