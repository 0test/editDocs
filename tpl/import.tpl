<div id="tab-page1" class="tab-page" style="display:block;">

    <script>
        $(document).ready(function()
        {
            $('#tpl,#checktv').SumoSelect();

            Dropzone.autoDiscover = false;
            $("div#fileuploader").dropzone({
                url: "/assets/modules/editdocs/ajax.php",
                paramName: "myfile",
                acceptedFiles: ".xls,.xlsx, .ods",
                dictDefaultMessage: "Перетащите сюда нужный EXCEL-файл или выберите по клику",
                init: function() {
                    this.on("success", function(file, responseText) {
                        //console.log(responseText);
                        //excelTable(responseText);
                        responseText = responseText.split("|");
                        $('#result_progress').html('<b>' + responseText[1] + '</b>');
                        $('#result').html(responseText[2]);
                        $('.sending').show(0);
                    });
                }
            });



            $('body').on('click', '#process', function () {
                $("#hidf").val($('.tabres tr:nth-child(1) td:nth-child(1)').html());
                var dada = $('form#pro').serialize();
                makeProgress(dada)
            }); //end click

            function makeProgress(dada) {
                loading();
                console.log(dada);
                $.ajax({
                    type: "POST",
                    url: "/assets/modules/editdocs/ajax.php",
                    data: dada,
                    success: function (result) {
                        resp = result.split("|");
                        $('#result').html(resp[2]);
                        if (parseInt(resp[0], 10) < parseInt(resp[1], 10)) {
                            $("#result_progress").html("<b>Импорт: " + resp[0] + " из " + resp[1] + "</b>");
                            makeProgress(dada);
                        } else {
                            $("#result_progress").html("<b>Импорт: " + resp[0] + " из " + resp[1] + "</b>");
                        }
                    }
                }); //end ajax
            }


            $('body').on('click', '#clear', function () {

                $.ajax({
                    type: "POST",
                    url: "/assets/modules/editdocs/ajax.php",
                    data: "clear=1",
                    success: function (result) {

                        $('#warning').html(result);
                        top.mainMenu.reloadtree();


                    }

                }); //end ajax
            }); //end click


        });

        function loading() {
            $('#result').html('<div class="loading">Загружаюсь...</div>');
        }

    </script>



    <div id="fileuploader"  class="dropzone"></div>
    <br/><br/>
    <div class="sending">
        <form id="pro">
            <div class="parf">
                ID родителя<br/>
                <input type="text" name="parimp" id="parent" class="inp" style="width: 70px"/>
            </div>
            <div class="parf" style="width: 300px">
                Шаблон<br/>

                <select id="tpl" name="tpl">
                    <option selected="selected" value="file">Из файла</option>
                    [+tpl+]
                    <option value="0">(blank)</option>
                </select>
            </div>
            <div class="parf">
                TV для проверки наличия в базе<br/>
                <select id="checktv" name="checktv">
                    <option value="0" selected="selected">без проверки</option>
                    <optgroup label="Основные поля">
                        [+fields+]
                    </optgroup>
                    <optgroup label="TV - параметры">
                        [+tvs+]
                    </optgroup>

                </select>
            </div>
            <div class="subbat">
                <button class="btn" id="process" type="button">ПОЕХАЛИ!</button> <input type="checkbox" id="test" name="test" value="1" /> Тестовый режим (без обновления)
            </div>
            <div class="mess">
                <div id="warning"></div>
                <br/>
                <button id="clear" type="button" class="btn"  style="min-width: 170px" > Сбросить кэш</button>
            </div>
            <input type="hidden" name="imp" value="1" />
            <br/>
        </form>
        <div class="clear"></div>
    </div>

    <br/><br/>
    <div id="result_progress"></div>
    <div id="result"></div>

</div>