const IDENTIFIERS = {
    SEARCHINPUT: 'downloadcenter-search-input',
    FORM: '#region-main form.mform',
    FORMELEMENTS: '.fitem',
    TOPICS: '.card.block',
    CHECKBOX: 'input[type="checkbox"]',
    TITLE: 'label .itemtitle span:not(.badge)',
    RESULTSHOLDER: 'downloadcenter-search-results',
    RESULTSCOUNT: 'downloadcenter-search-results-count'
};

const allCmsPerTopic = [];
let resultsHolder = null;
let resultsCount = null;


const search = (e) => {
    const searchValue = e.target.value.toLowerCase();
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
    } else {
        resultsHolder.classList.add('d-none');
    }
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
    const searchInput = document.getElementById(IDENTIFIERS.SEARCHINPUT);
    searchInput.addEventListener('input', search);
    const form = document.querySelector(IDENTIFIERS.FORM);
    const topics = form.querySelectorAll(IDENTIFIERS.TOPICS);
    resultsHolder = document.getElementById(IDENTIFIERS.RESULTSHOLDER);
    resultsCount = document.getElementById(IDENTIFIERS.RESULTSCOUNT);
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