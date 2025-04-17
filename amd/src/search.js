const IDENTIFIERS = {
    SEARCHINPUT: 'downloadcenter-search-input',
    FORM: '#region-main form.mform',
    FORMELEMENTS: '.fitem',
    TOPICS: '.card.block',
    CHECKBOX: 'input[type="checkbox"]',
    TITLE: 'label .itemtitle span:not(.badge)',
    RESULTSHOLDER: 'downloadcenter-search-results',
    RESULTSCOUNT: 'downloadcenter-search-results-count',
    SEARCHCLEAR: '#downloadcenter-search-clear'
};

const allCmsPerTopic = [];
let resultsHolder = null;
let resultsCount = null;
let searchClearBtn = null;
let searchInput = null;


const search = (searchValue) => {
    const showAll = searchValue.length === 0;
    let resultscount = 0;
    allCmsPerTopic.forEach(topic => {
        let foundInTopic = false;
        topic.cms.forEach(cm => {
            if (cm.title.indexOf(searchValue) > -1 || showAll) {
                foundInTopic = true;
                resultscount++;
                cm.visible = true;
                cm.elem.classList.remove('d-none');
            } else {
                cm.visible = false;
                cm.elem.classList.add('d-none');
            }
        });
        if (foundInTopic) {
            topic.visible = true;
            topic.elem.classList.remove('d-none');
        } else {
            topic.visible = false;
            topic.elem.classList.add('d-none');
        }
    });
    if (!showAll) {
        resultsCount.textContent = resultscount;
        resultsHolder.classList.remove('d-none');
        searchClearBtn.classList.remove('d-none');
    } else {
        resultsHolder.classList.add('d-none');
        searchClearBtn.classList.add('d-none');
    }
};

const searchClear = (e) => {
    e.preventDefault();
    searchInput.value = '';
    search('');
};

const submitForm = () => {
    // We need to make sure that if a topic or a cm is not visible, it's checkbox is not checked.
    allCmsPerTopic.forEach(topic => {
        if (!topic.visible && topic.checkbox.checked) {
            topic.checkbox.checked = false;
        }
        topic.cms.forEach(cm => {
            if (!cm.visible && cm.checkbox.checked) {
                cm.checkbox.checked = false;
            }
        });
    });
    return true;
};

export const init = () => {
    searchInput = document.getElementById(IDENTIFIERS.SEARCHINPUT);
    searchInput.addEventListener('input', (e) => {
 search(e.target.value.toLowerCase());
});
    const form = document.querySelector(IDENTIFIERS.FORM);
    const topics = form.querySelectorAll(IDENTIFIERS.TOPICS);
    resultsHolder = document.getElementById(IDENTIFIERS.RESULTSHOLDER);
    resultsCount = document.getElementById(IDENTIFIERS.RESULTSCOUNT);
    searchClearBtn = document.querySelector(IDENTIFIERS.SEARCHCLEAR);
    searchClearBtn.addEventListener('click', searchClear);

    form.addEventListener('submit', submitForm);
    topics.forEach(topic => {
        const elements = topic.querySelectorAll(IDENTIFIERS.FORMELEMENTS);
        const topicObj = {};
        topicObj.elem = topic;
        topicObj.cms = [];
        topicObj.visible = true;
        topicObj.checkbox = topic.querySelector(IDENTIFIERS.CHECKBOX);
        elements.forEach(element => {
            const title = element.querySelector(IDENTIFIERS.TITLE);
            if (title) {
                const cmObj = {};
                cmObj.title = title.textContent.toLowerCase();
                cmObj.elem = element;
                cmObj.visible = true;
                cmObj.checkbox = element.querySelector(IDENTIFIERS.CHECKBOX);
                topicObj.cms.push(cmObj);
            }
        });
        allCmsPerTopic.push(topicObj);
    });
};